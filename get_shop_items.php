<?php
// ============================================================
//  UniBites API — Get Shop's Own Items (full menu, not just top sellers)
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$shop_id = requireShop();

$stmt = $conn->prepare("
    SELECT f.id, f.name, f.price, f.image, f.is_available, f.avg_rating, f.rating_count,
           c.name AS category
    FROM foods f
    LEFT JOIN categories c ON f.category_id = c.id
    WHERE f.shop_id = ?
    ORDER BY f.sort_order ASC, f.id ASC
");
$stmt->bind_param('i', $shop_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id'            => (int)$row['id'],
        'name'          => $row['name'],
        'price'         => (float)$row['price'],
        'image'         => $row['image'],
        'category'      => $row['category'] ?? 'Uncategorized',
        'available'     => (bool)$row['is_available'],
        'avg_rating'    => (float)$row['avg_rating'],
        'rating_count'  => (int)$row['rating_count'],
    ];
}

jsonResponse(true, '', ['items' => $items]);
?>