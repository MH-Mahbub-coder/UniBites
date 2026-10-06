<?php
// ============================================================
//  UniBites API — Update Order Status (shop / admin only)
//  Riders use update_delivery_status.php; customers use cancel_order.php.
//  A shop can only move ITS OWN order one step forward:
//      pending -> confirmed -> preparing -> ready
//  POST JSON: { order_id, status, note }
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireLogin();
$role = $_SESSION['user_role'] ?? '';
if ($role !== 'shop' && $role !== 'admin') {
    jsonResponse(false, 'Not permitted.', ['code' => 'wrong_role']);
}

// new status => the status the order must currently be in
const SHOP_TRANSITIONS = ['confirmed' => 'pending', 'preparing' => 'confirmed', 'ready' => 'preparing'];
$allStatuses = ['pending','confirmed','preparing','ready','rider_accepted','picked_up','out_for_delivery','delivered','completed','cancelled'];

$data     = json_decode(file_get_contents('php://input'), true) ?? [];
$order_id = (int)($data['order_id'] ?? 0);
$status   = trim($data['status'] ?? '');
$note     = trim($data['note'] ?? '');

if (!$order_id || !in_array($status, $allStatuses, true)) {
    jsonResponse(false, 'Invalid order or status.');
}

$stmt = $conn->prepare("SELECT shop_id, status FROM orders WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) jsonResponse(false, 'Order not found.');

$userId = (int)$_SESSION['user_id'];

$conn->begin_transaction();
try {
    if ($role === 'shop') {
        if ((int)$order['shop_id'] !== (int)$_SESSION['shop_id']) {
            $conn->rollback();
            jsonResponse(false, 'You do not have permission to update this order.');
        }
        if (!isset(SHOP_TRANSITIONS[$status])) {
            $conn->rollback();
            jsonResponse(false, 'Shops can only confirm, prepare, and mark orders ready.');
        }
        $required = SHOP_TRANSITIONS[$status];
        // Conditional update: only succeeds if the order is STILL in the expected state
        $upd = $conn->prepare("UPDATE orders SET status = ? WHERE id = ? AND status = ?");
        $upd->bind_param('sis', $status, $order_id, $required);
        $upd->execute();
        if ($upd->affected_rows !== 1) {
            $conn->rollback();
            jsonResponse(false, "This order's status has changed. Refresh and try again.");
        }
    } else {
        $upd = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $upd->bind_param('si', $status, $order_id);
        $upd->execute();
    }

    $log = $conn->prepare("INSERT INTO order_status_log (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)");
    $log->bind_param('issi', $order_id, $status, $note, $userId);
    $log->execute();

    $conn->commit();
    jsonResponse(true, 'Order status updated.');
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(false, 'Update failed: ' . $e->getMessage());
}
?>