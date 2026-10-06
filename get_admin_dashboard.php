<?php
// ============================================================
//  UniBites API — Admin Dashboard (platform-wide stats)
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireAdmin();

function scalar($conn, $sql) {
    $row = $conn->query($sql)->fetch_row();
    return $row[0];
}

$done = "('delivered','completed')";

$stats = [
    'total_students'   => (int)scalar($conn, "SELECT COUNT(*) FROM users WHERE role = 'student'"),
    'active_shops'     => (int)scalar($conn, "SELECT COUNT(*) FROM shops WHERE is_active = 1"),
    'active_riders'    => (int)scalar($conn, "SELECT COUNT(*) FROM users WHERE role = 'student' AND is_rider = 1 AND is_active = 1"),
    'pending_orders'   => (int)scalar($conn, "SELECT COUNT(*) FROM orders WHERE status IN ('pending','confirmed','preparing','ready')"),
    'total_orders'     => (int)scalar($conn, "SELECT COUNT(*) FROM orders"),
    'completed_orders' => (int)scalar($conn, "SELECT COUNT(*) FROM orders WHERE status IN $done"),
    'total_revenue'    => (float)scalar($conn, "SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN $done"),
    'rider_payouts'    => (float)scalar($conn, "SELECT COALESCE(SUM(rider_earn),0) FROM deliveries WHERE status = 'delivered'"),
];

// Revenue per active shop, last 30 days
$byShop = $conn->query("
    SELECT s.name, COALESCE(SUM(o.total),0) AS revenue
    FROM shops s
    LEFT JOIN orders o ON o.shop_id = s.id AND o.status IN $done
         AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE s.is_active = 1
    GROUP BY s.id
    ORDER BY s.sort_order ASC, s.id ASC
")->fetch_all(MYSQLI_ASSOC);
foreach ($byShop as &$r) $r['revenue'] = (float)$r['revenue'];
unset($r);

// Top shops, all time
$topShops = $conn->query("
    SELECT s.name, COUNT(o.id) AS orders, COALESCE(SUM(o.total),0) AS revenue
    FROM shops s
    LEFT JOIN orders o ON o.shop_id = s.id AND o.status IN $done
    GROUP BY s.id
    ORDER BY revenue DESC
    LIMIT 4
")->fetch_all(MYSQLI_ASSOC);
foreach ($topShops as &$r) { $r['orders'] = (int)$r['orders']; $r['revenue'] = (float)$r['revenue']; }
unset($r);

// Top riders, by completed deliveries
$topRiders = $conn->query("
    SELECT u.first_name, u.last_name, COUNT(d.id) AS deliveries, COALESCE(SUM(d.rider_earn),0) AS earned
    FROM deliveries d
    JOIN users u ON d.rider_id = u.id
    WHERE d.status = 'delivered'
    GROUP BY u.id
    ORDER BY deliveries DESC
    LIMIT 4
")->fetch_all(MYSQLI_ASSOC);
foreach ($topRiders as &$r) {
    $r['name'] = trim($r['first_name'] . ' ' . $r['last_name']);
    $r['deliveries'] = (int)$r['deliveries'];
    $r['earned'] = (float)$r['earned'];
    unset($r['first_name'], $r['last_name']);
}
unset($r);

jsonResponse(true, '', [
    'stats' => $stats,
    'revenue_by_shop' => $byShop,
    'top_shops' => $topShops,
    'top_riders' => $topRiders,
]);
?>