<?php
// ============================================================
//  UniBites API — Rider picks an order
//  Claiming is atomic: if two riders click at the same moment, the
//  conditional UPDATE lets only one of them win.
//  POST JSON: { order_id }
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$rider_id = requireRider();

$data     = json_decode(file_get_contents('php://input'), true) ?? [];
$order_id = (int)($data['order_id'] ?? 0);
if (!$order_id) jsonResponse(false, 'Order ID is required.');

$stmt = $conn->prepare("SELECT user_id, delivery_type FROM orders WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) jsonResponse(false, 'Order not found.');
if ((int)$order['user_id'] === $rider_id) jsonResponse(false, "You can't deliver your own order.");
if ($order['delivery_type'] !== 'delivery') jsonResponse(false, 'This order is for pickup, not delivery.');

$cnt = $conn->prepare("SELECT COUNT(*) FROM deliveries WHERE rider_id = ? AND status IN ('accepted','picked_up','out_for_delivery')");
$cnt->bind_param('i', $rider_id);
$cnt->execute();
if ((int)$cnt->get_result()->fetch_row()[0] >= MAX_ACTIVE_DELIVERIES) {
    jsonResponse(false, 'You already have ' . MAX_ACTIVE_DELIVERIES . ' active deliveries. Finish one before picking another.');
}

$conn->begin_transaction();
try {
    // Claim the delivery — succeeds for exactly one rider
    $claim = $conn->prepare("
        UPDATE deliveries SET rider_id = ?, status = 'accepted', accepted_at = NOW()
        WHERE order_id = ? AND rider_id IS NULL AND status = 'pending'
    ");
    $claim->bind_param('ii', $rider_id, $order_id);
    $claim->execute();
    if ($claim->affected_rows !== 1) {
        $conn->rollback();
        jsonResponse(false, 'Sorry, another rider just picked this order.');
    }

    // The order must still be READY (the customer may have cancelled, etc.)
    $upd = $conn->prepare("UPDATE orders SET rider_id = ?, status = 'rider_accepted' WHERE id = ? AND status = 'ready'");
    $upd->bind_param('ii', $rider_id, $order_id);
    $upd->execute();
    if ($upd->affected_rows !== 1) {
        $conn->rollback();
        jsonResponse(false, 'This order is no longer available.');
    }

    $log = $conn->prepare("INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, 'rider_accepted', 'A rider accepted this delivery', ?)");
    $log->bind_param('ii', $order_id, $rider_id);
    $log->execute();

    $conn->commit();
    jsonResponse(true, 'Order picked! Head to the shop to collect it.');
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(false, 'Could not pick this order: ' . $e->getMessage());
}
?>