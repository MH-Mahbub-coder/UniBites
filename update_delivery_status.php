<?php
// ============================================================
//  UniBites API — Rider updates one of THEIR deliveries
//  POST JSON: { order_id, action: 'picked_up' | 'delivered' | 'release' }
//    picked_up : rider collected the food from the shop
//    delivered : handed to the customer -> credits the rider's earnings
//    release   : rider gives up (only before collecting the food)
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$rider_id = requireRider();

$data     = json_decode(file_get_contents('php://input'), true) ?? [];
$order_id = (int)($data['order_id'] ?? 0);
$action   = $data['action'] ?? '';

if (!$order_id || !in_array($action, ['picked_up', 'delivered', 'release'], true)) {
    jsonResponse(false, 'Invalid request.');
}

$stmt = $conn->prepare("
    SELECT o.id, o.payment_method, d.id AS delivery_id, d.rider_id, d.rider_earn
    FROM orders o
    JOIN deliveries d ON d.order_id = o.id
    WHERE o.id = ? LIMIT 1
");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row || (int)$row['rider_id'] !== $rider_id) {
    jsonResponse(false, 'This is not one of your deliveries.');
}
$delivery_id = (int)$row['delivery_id'];

// Runs an UPDATE and reports whether exactly one row changed
function changedOne($conn, $sql, $types, ...$params) {
    $s = $conn->prepare($sql);
    $s->bind_param($types, ...$params);
    $s->execute();
    return $s->affected_rows === 1;
}
function logStatus($conn, $order_id, $status, $note, $user_id) {
    $s = $conn->prepare("INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)");
    $s->bind_param('issi', $order_id, $status, $note, $user_id);
    $s->execute();
}
$stale = "This delivery has already moved on. Refresh and try again.";

$conn->begin_transaction();
try {
    if ($action === 'picked_up') {
        if (!changedOne($conn, "UPDATE deliveries SET status = 'picked_up', picked_up_at = NOW() WHERE id = ? AND rider_id = ? AND status = 'accepted'", 'ii', $delivery_id, $rider_id)
         || !changedOne($conn, "UPDATE orders SET status = 'picked_up' WHERE id = ? AND status = 'rider_accepted'", 'i', $order_id)) {
            $conn->rollback(); jsonResponse(false, $stale);
        }
        logStatus($conn, $order_id, 'picked_up', 'Rider collected the order from the shop', $rider_id);
        $conn->commit();
        jsonResponse(true, 'Marked as picked up. Now deliver it to the customer!');
    }

    if ($action === 'delivered') {
        $earn = (float)$row['rider_earn'];

        if (!changedOne($conn, "UPDATE deliveries SET status = 'delivered', delivered_at = NOW() WHERE id = ? AND rider_id = ? AND status IN ('picked_up','out_for_delivery')", 'ii', $delivery_id, $rider_id)
         || !changedOne($conn, "UPDATE orders SET status = 'delivered', payment_status = IF(payment_method = 'cash_on_delivery', 'paid', payment_status) WHERE id = ? AND status IN ('picked_up','out_for_delivery')", 'i', $order_id)) {
            $conn->rollback(); jsonResponse(false, $stale);
        }

        // Credit the rider
        $pay = $conn->prepare("UPDATE users SET balance = balance + ?, total_earnings = total_earnings + ? WHERE id = ?");
        $pay->bind_param('ddi', $earn, $earn, $rider_id);
        $pay->execute();

        // Cash on delivery: the rider collected the cash, so the payment record is now settled
        $pr = $conn->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE order_id = ? AND method = 'cash_on_delivery' AND status = 'pending'");
        $pr->bind_param('i', $order_id);
        $pr->execute();

        logStatus($conn, $order_id, 'delivered', 'Delivered to the customer', $rider_id);
        $conn->commit();
        jsonResponse(true, "Delivered! You earned $earn TK.", ['earned' => $earn]);
    }

    // release
    if (!changedOne($conn, "UPDATE deliveries SET rider_id = NULL, status = 'pending', accepted_at = NULL WHERE id = ? AND rider_id = ? AND status = 'accepted'", 'ii', $delivery_id, $rider_id)
     || !changedOne($conn, "UPDATE orders SET rider_id = NULL, status = 'ready' WHERE id = ? AND status = 'rider_accepted'", 'i', $order_id)) {
        $conn->rollback();
        jsonResponse(false, "You can only give up a delivery before you've collected the food.");
    }
    logStatus($conn, $order_id, 'ready', 'Rider gave up this delivery — order is back on the list', $rider_id);
    $conn->commit();
    jsonResponse(true, 'Delivery released. The order is back on the list for other riders.');

} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(false, 'Update failed: ' . $e->getMessage());
}
?>