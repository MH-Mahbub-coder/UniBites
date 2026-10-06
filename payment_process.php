<?php
// ============================================================
//  UniBites API — Online Payment Processor
//  Handles SSLCommerz IPN callback & manual payment simulation
//  POST: { order_id, card_number(last4), card_holder,
//          expiry, cvv, method: 'card'|'bkash'|'rocket'|'nagad' }
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Please login.');
}

$data     = json_decode(file_get_contents('php://input'), true);
$order_id = (int)($data['order_id'] ?? 0);
$method   = sanitize($conn, $data['method'] ?? 'card');
$user_id  = (int)$_SESSION['user_id'];

if (!$order_id) {
    jsonResponse(false, 'Invalid order.');
}

// Verify order belongs to user
$ord = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$ord->bind_param('ii', $order_id, $user_id);
$ord->execute();
$order = $ord->get_result()->fetch_assoc();

if (!$order) {
    jsonResponse(false, 'Order not found.');
}

if ($order['payment_status'] === 'paid') {
    jsonResponse(false, 'Order already paid.');
}

// Simulate payment gateway (Replace with real SSLCommerz SDK in production)
// Generate fake transaction ID
$txn_id = strtoupper('UB' . date('YmdHis') . rand(1000, 9999));

// Simulate 95% success rate (or always succeed in dev)
$payment_success = true; // Set to: (rand(1,100) <= 95) for simulation

$conn->begin_transaction();

try {
    if ($payment_success) {
        // Update payment record
        $upd_pay = $conn->prepare("
            UPDATE payments
            SET status = 'paid', method = ?, transaction_id = ?, paid_at = NOW()
            WHERE order_id = ? AND user_id = ?
        ");
        $upd_pay->bind_param('ssii', $method, $txn_id, $order_id, $user_id);
        $upd_pay->execute();

        // Update order payment status
        $upd_ord = $conn->prepare("
            UPDATE orders
            SET payment_status = 'paid', transaction_id = ?, status = 'confirmed'
            WHERE id = ?
        ");
        $upd_ord->bind_param('si', $txn_id, $order_id);
        $upd_ord->execute();

        // Notification
        $notif = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, 'Payment Successful!', ?, 'payment_success')
        ");
        $notif_msg = "Payment of BDT {$order['total']} for order #{$order['order_number']} was successful. TXN: $txn_id";
        $notif->bind_param('is', $user_id, $notif_msg);
        $notif->execute();

        $conn->commit();

        jsonResponse(true, 'Payment successful!', [
            'transaction_id' => $txn_id,
            'amount'         => $order['total'],
            'method'         => $method,
            'order_number'   => $order['order_number'],
            'redirect'       => "/unibites/pages/order_success.php?order_id=$order_id"
        ]);
    } else {
        // Payment failed
        $upd_pay = $conn->prepare("
            UPDATE payments SET status = 'failed' WHERE order_id = ? AND user_id = ?
        ");
        $upd_pay->bind_param('ii', $order_id, $user_id);
        $upd_pay->execute();

        $notif = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, 'Payment Failed', ?, 'payment_failed')
        ");
        $notif_msg = "Payment failed for order #{$order['order_number']}. Please try again.";
        $notif->bind_param('is', $user_id, $notif_msg);
        $notif->execute();

        $conn->commit();
        jsonResponse(false, 'Payment failed. Please try again.');
    }
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(false, 'Payment error: ' . $e->getMessage());
}
?>