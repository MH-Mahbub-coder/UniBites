<?php
// ============================================================
//  UniBites API — Shop revenue analytics
//  Only orders a rider has marked delivered count, dated by delivery time.
//  Revenue = food value (subtotal); the delivery fee isn't the shop's money.
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$shop_id = requireShop();

function revenueSince($conn, $shop_id, $sinceExpr) {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(o.subtotal),0) FROM orders o
        JOIN deliveries d ON d.order_id = o.id
        WHERE o.shop_id = ? AND d.status = 'delivered' AND d.delivered_at >= $sinceExpr
    ");
    $stmt->bind_param('i', $shop_id);
    $stmt->execute();
    return (float)$stmt->get_result()->fetch_row()[0];
}

$today  = revenueSince($conn, $shop_id, 'CURDATE()');
$last7  = revenueSince($conn, $shop_id, 'DATE_SUB(NOW(), INTERVAL 7 DAY)');
$last30 = revenueSince($conn, $shop_id, 'DATE_SUB(NOW(), INTERVAL 30 DAY)');

// Daily totals for the last 7 days, with empty days filled in as 0
$dailyStmt = $conn->prepare("
    SELECT DATE(d.delivered_at) AS day, SUM(o.subtotal) AS total
    FROM orders o JOIN deliveries d ON d.order_id = o.id
    WHERE o.shop_id = ? AND d.status = 'delivered' AND d.delivered_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(d.delivered_at)
");
$dailyStmt->bind_param('i', $shop_id);
$dailyStmt->execute();
$byDay = [];
foreach ($dailyStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $byDay[$r['day']] = (float)$r['total'];
}
$daily = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $daily[] = ['day' => date('D', strtotime($d)), 'val' => $byDay[$d] ?? 0.0];
}

// Top 4 items by units sold in the last 30 days, with their live rating
$topStmt = $conn->prepare("
    SELECT f.name, f.avg_rating, f.rating_count,
           SUM(oi.quantity) AS sold, SUM(oi.total_price) AS revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN deliveries d ON d.order_id = o.id
    JOIN foods f ON oi.food_id = f.id
    WHERE o.shop_id = ? AND d.status = 'delivered' AND d.delivered_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY f.id
    ORDER BY sold DESC
    LIMIT 4
");
$topStmt->bind_param('i', $shop_id);
$topStmt->execute();
$topItems = $topStmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($topItems as &$it) {
    $it['sold'] = (int)$it['sold'];
    $it['revenue'] = (float)$it['revenue'];
    $it['avg_rating'] = (float)$it['avg_rating'];
    $it['rating_count'] = (int)$it['rating_count'];
}
unset($it);

$avgQ = $conn->prepare("SELECT SUM(avg_rating * rating_count) / SUM(rating_count) FROM foods WHERE shop_id = ? AND rating_count > 0");
$avgQ->bind_param('i', $shop_id);
$avgQ->execute();
$avg = $avgQ->get_result()->fetch_row()[0];

jsonResponse(true, '', [
    'today' => $today, 'last7' => $last7, 'last30' => $last30,
    'daily' => $daily, 'top_items' => $topItems,
    'shop_avg_rating' => $avg !== null ? round((float)$avg, 1) : null,
]);
?>