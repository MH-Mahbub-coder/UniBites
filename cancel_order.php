<?php
// ============================================================
//  UniBites API — Cancel Order
//  Customers can only cancel while the order is still pending or
//  confirmed — once a shop starts preparing it, it's too late.
//  POST JSON: { order_id }
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireStudent();
$user_id = (int)$_SESSION['user_id'];

$data     = json_decode(file_get_contents('php://input'), true);
$order_id = (int)($data['order_id'] ?? 0);

if (!$order_id) jsonResponse(false, 'Order ID is required.');

$stmt = $conn->prepare("SELECT user_id, status FROM orders WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) jsonResponse(false, 'Order not found.');
if ((int)$order['user_id'] !== $user_id) jsonResponse(false, 'You do not own this order.');
if (!in_array($order['status'], ['pending', 'confirmed'])) {
    jsonResponse(false, 'This order can no longer be cancelled.');
}

$conn->begin_transaction();
try {
    $upd = $conn->prepare("UPDATE orders SET status = 'cancelled', cancelled_reason = 'Cancelled by customer' WHERE id = ?");
    $upd->bind_param('i', $order_id);
    $upd->execute();

    $log = $conn->prepare("INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, 'cancelled', 'Cancelled by customer', ?)");
    $log->bind_param('ii', $order_id, $user_id);
    $log->execute();

    $conn->commit();
    jsonResponse(true, 'Order cancelled.');
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(false, 'Cancellation failed: ' . $e->getMessage());
}
?>