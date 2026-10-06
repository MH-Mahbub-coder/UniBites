<?php
// ============================================================
//  UniBites API — Admin: list all shops (active and inactive)
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireAdmin();

$rows = $conn->query("
    SELECT s.id, s.name, s.slug, s.location, s.description, s.is_active,
           (SELECT COUNT(*) FROM foods  f WHERE f.shop_id = s.id) AS item_count,
           (SELECT COUNT(*) FROM orders o WHERE o.shop_id = s.id) AS order_count
    FROM shops s
    ORDER BY s.sort_order ASC, s.id ASC
")->fetch_all(MYSQLI_ASSOC);

$shops = array_map(function ($r) {
    return [
        'id'          => (int)$r['id'],
        'name'        => $r['name'],
        'slug'        => $r['slug'],
        'email'       => $r['slug'] . '@uiu.ac.bd',
        'location'    => $r['location'],
        'description' => $r['description'],
        'active'      => (bool)$r['is_active'],
        'item_count'  => (int)$r['item_count'],
        'order_count' => (int)$r['order_count'],
    ];
}, $rows);

jsonResponse(true, '', ['shops' => $shops]);
?>