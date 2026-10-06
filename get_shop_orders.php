<?php
// ============================================================
//  UniBites API — This shop's orders
//  GET ?status=pending (default: everything not yet delivered)
//      ?status=delivered
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$shop_id = requireShop();
$filter  = ($_GET['status'] ?? 'pending') === 'delivered' ? 'delivered' : 'pending';

$statusClause = $filter === 'delivered'
    ? "o.status IN ('delivered','completed')"
    : "o.status IN ('pending','confirmed','preparing','ready','rider_accepted','picked_up','out_for_delivery')";

$stmt = $conn->prepare("
    SELECT o.id, o.order_number, o.full_name, o.subtotal, o.delivery_type, o.status, o.created_at,
           CONCAT(r.first_name, ' ', COALESCE(r.last_name, '')) AS rider_name
    FROM orders o
    LEFT JOIN users r ON o.rider_id = r.id
    WHERE o.shop_id = ? AND $statusClause
    ORDER BY o.created_at DESC
    LIMIT 50
");
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$itemStmt = $conn->prepare("SELECT food_name, quantity FROM order_items WHERE order_id = ?");
foreach ($orders as &$o) {
    $itemStmt->bind_param('i', $o['id']);
    $itemStmt->execute();
    $o['items'] = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $o['id'] = (int)$o['id'];
    $o['subtotal'] = (float)$o['subtotal'];
    $o['rider_name'] = $o['rider_name'] !== null ? trim($o['rider_name']) : null;
    $ts = strtotime($o['created_at']);
    $o['time'] = date('g:i A', $ts);
    $o['date'] = date('j-n-Y', $ts);
    unset($o['created_at']);
}
unset($o);

jsonResponse(true, '', ['orders' => $orders]);
?>