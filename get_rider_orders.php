<?php
// ============================================================
//  UniBites API — Delivery requests available to a rider
//  An order shows up once its shop marks it READY and nobody has picked it.
//  A rider never sees their own orders, and the customer's phone number
//  is NOT included here (it's revealed after the rider picks the order).
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$rider_id = requireRider();

$stmt = $conn->prepare("
    SELECT o.id, o.order_number, o.full_name, o.location, o.subtotal, o.delivery_fee, o.total,
           o.payment_method, o.created_at, d.rider_earn, s.name AS shop_name
    FROM orders o
    JOIN deliveries d ON d.order_id = o.id
    JOIN shops s ON o.shop_id = s.id
    WHERE o.status = 'ready' AND o.delivery_type = 'delivery'
      AND d.status = 'pending' AND d.rider_id IS NULL
      AND o.user_id <> ?
    ORDER BY o.created_at ASC
    LIMIT 50
");
$stmt->bind_param('i', $rider_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$itemStmt = $conn->prepare("
    SELECT oi.food_name, oi.quantity, oi.total_price, f.image
    FROM order_items oi
    LEFT JOIN foods f ON oi.food_id = f.id
    WHERE oi.order_id = ?
");
foreach ($orders as &$o) {
    $itemStmt->bind_param('i', $o['id']);
    $itemStmt->execute();
    $items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($items as &$it) {
        $it['quantity'] = (int)$it['quantity'];
        $it['total_price'] = (float)$it['total_price'];
    }
    unset($it);
    $o['items'] = $items;

    $o['id'] = (int)$o['id'];
    $o['subtotal'] = (float)$o['subtotal'];
    $o['delivery_fee'] = (float)$o['delivery_fee'];
    $o['total'] = (float)$o['total'];
    $o['rider_earn'] = (float)$o['rider_earn'];
    $ts = strtotime($o['created_at']);
    $o['time'] = date('g:i A', $ts);
    $o['date'] = date('j-n-Y', $ts);
    unset($o['created_at']);
}
unset($o);

$cnt = $conn->prepare("SELECT COUNT(*) FROM deliveries WHERE rider_id = ? AND status IN ('accepted','picked_up','out_for_delivery')");
$cnt->bind_param('i', $rider_id);
$cnt->execute();
$active = (int)$cnt->get_result()->fetch_row()[0];

jsonResponse(true, '', [
    'orders' => $orders,
    'active_deliveries' => $active,
    'max_active' => MAX_ACTIVE_DELIVERIES,
]);
?>