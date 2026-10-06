<?php
// ============================================================
//  UniBites API — Get Categories
//  Feeds the "Food Category" filter list on foods.html AND the
//  category dropdown shops use in Manage Items — one shared source,
//  so they can never drift out of sync (this was flagged early on).
// ============================================================
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$sql = "
    SELECT c.id, c.name, c.slug, COUNT(f.id) AS food_count
    FROM categories c
    LEFT JOIN foods f ON f.category_id = c.id AND f.is_available = 1
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.sort_order ASC
";
$result = $conn->query($sql);

$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = [
        'id'         => (int)$row['id'],
        'name'       => $row['name'],
        'slug'       => $row['slug'],
        'food_count' => (int)$row['food_count'],
    ];
}

jsonResponse(true, '', ['categories' => $categories]);
?>