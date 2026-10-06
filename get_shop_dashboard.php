<?php
// ============================================================
//  UniBites API — Shop dashboard stats
//  Revenue = food value (subtotal) of orders a rider has marked delivered.
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$shop_id = requireShop();

function one($conn, $sql, $shop_id) {
    $q = $conn->prepare($sql);
    $q->bind_param('i', $shop_id);
    $q->execute();
    return $q->get_result()->fetch_row()[0];
}

$active = "('pending','confirmed','preparing','ready','rider_accepted','picked_up','out_for_delivery')";

$stats = [
    'pending_orders'   => (int)one($conn, "SELECT COUNT(*) FROM orders WHERE shop_id = ? AND status IN $active", $shop_id),
    'delivered_orders' => (int)one($conn, "SELECT COUNT(*) FROM orders WHERE shop_id = ? AND status IN ('delivered','completed')", $shop_id),
    'active_items'     => (int)one($conn, "SELECT COUNT(*) FROM foods WHERE shop_id = ? AND is_available = 1", $shop_id),
    'today_revenue'    => (float)one($conn, "
        SELECT COALESCE(SUM(o.subtotal),0) FROM orders o
        JOIN deliveries d ON d.order_id = o.id
        WHERE o.shop_id = ? AND d.status = 'delivered' AND d.delivered_at >= CURDATE()", $shop_id),
];

// Weighted by number of ratings, so an item with 100 ratings counts more than one with 2
$r = one($conn, "SELECT SUM(avg_rating * rating_count) / SUM(rating_count) FROM foods WHERE shop_id = ? AND rating_count > 0", $shop_id);
$stats['shop_avg_rating'] = $r !== null ? round((float)$r, 1) : null;

$recentStmt = $conn->prepare("
    SELECT o.id, o.order_number, o.full_name, o.subtotal, o.status
    FROM orders o
    WHERE o.shop_id = ? AND o.status IN $active
    ORDER BY o.created_at DESC LIMIT 5
");
$recentStmt->bind_param('i', $shop_id);
$recentStmt->execute();
$recent = $recentStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$itemStmt = $conn->prepare("SELECT food_name, quantity FROM order_items WHERE order_id = ?");
foreach ($recent as &$o) {
    $itemStmt->bind_param('i', $o['id']);
    $itemStmt->execute();
    $items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $o['items_summary'] = implode(', ', array_map(fn($i) => $i['quantity'] . 'x ' . $i['food_name'], $items));
    $o['id'] = (int)$o['id'];
    $o['subtotal'] = (float)$o['subtotal'];
}
unset($o);

jsonResponse(true, '', ['stats' => $stats, 'recent_orders' => $recent]);
?>