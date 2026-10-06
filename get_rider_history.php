<?php
// ============================================================
//  UniBites API — This rider's deliveries + earnings summary
//  In-progress deliveries come first, then delivered ones (newest first).
//  Contact numbers are included here because the rider owns the delivery.
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$rider_id = requireRider();

$stmt = $conn->prepare("
    SELECT o.id, o.order_number, o.full_name, o.phone, o.location, o.subtotal, o.delivery_fee,
           o.total, o.payment_method, o.created_at,
           d.status AS d_status, d.rider_earn,
           s.name AS shop_name, s.location AS shop_location,
           rr.rating AS order_rating
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    JOIN shops s ON o.shop_id = s.id
    LEFT JOIN rider_ratings rr ON rr.delivery_id = d.id
    WHERE d.rider_id = ? AND d.status <> 'cancelled'
    ORDER BY (d.status = 'delivered') ASC, COALESCE(d.delivered_at, d.accepted_at) DESC
    LIMIT 100
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
    $o['order_rating'] = $o['order_rating'] !== null ? (int)$o['order_rating'] : null;
    $ts = strtotime($o['created_at']);
    $o['time'] = date('g:i A', $ts);
    $o['date'] = date('j-n-Y', $ts);
    unset($o['created_at']);
}
unset($o);

$sumStmt = $conn->prepare("
    SELECT COUNT(*) AS total,
           SUM(status = 'delivered') AS delivered,
           SUM(status IN ('accepted','picked_up','out_for_delivery')) AS in_progress,
           COALESCE(SUM(CASE WHEN status = 'delivered' THEN rider_earn END), 0) AS earned,
           COALESCE(SUM(CASE WHEN status = 'delivered' AND delivered_at >= CURDATE() THEN rider_earn END), 0) AS today,
           COALESCE(SUM(CASE WHEN status = 'delivered' AND delivered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN rider_earn END), 0) AS week
    FROM deliveries
    WHERE rider_id = ? AND status <> 'cancelled'
");
$sumStmt->bind_param('i', $rider_id);
$sumStmt->execute();
$s = $sumStmt->get_result()->fetch_assoc();

$uStmt = $conn->prepare("SELECT avg_rating, rating_count, balance FROM users WHERE id = ?");
$uStmt->bind_param('i', $rider_id);
$uStmt->execute();
$u = $uStmt->get_result()->fetch_assoc();

jsonResponse(true, '', [
    'orders' => $orders,
    'summary' => [
        'total'        => (int)$s['total'],
        'delivered'    => (int)$s['delivered'],
        'in_progress'  => (int)$s['in_progress'],
        'earned'       => (float)$s['earned'],
        'today'        => (float)$s['today'],
        'week'         => (float)$s['week'],
        'avg_rating'   => (float)$u['avg_rating'],
        'rating_count' => (int)$u['rating_count'],
        'balance'      => (float)$u['balance'],
    ],
]);
?>