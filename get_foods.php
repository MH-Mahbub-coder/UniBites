<?php
// ============================================================
//  UniBites API — Get Foods (with live ratings)
//  GET params (all optional):
//    ?shop=<slug>        filter by one shop
//    ?category=<slug>    filter by one category
//    ?search=<text>      name search
//    ?max_price=<num>    price ceiling
//    ?popular=1          prefer popular items first, but still fills
//                         up to the limit with other items if there
//                         aren't enough flagged as popular yet
//    ?limit=<num>        default 100
// ============================================================
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$shop_slug    = sanitize($conn, $_GET['shop'] ?? '');
$category     = sanitize($conn, $_GET['category'] ?? '');
$search       = sanitize($conn, $_GET['search'] ?? '');
$max_price    = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$popular_only = isset($_GET['popular']) && $_GET['popular'] == '1';
$limit        = isset($_GET['limit']) ? min((int)$_GET['limit'], 200) : 100;

$sql = "SELECT f.id, f.name, f.price, f.image, f.tag, f.is_popular, f.is_special,
               f.avg_rating, f.rating_count,
               s.name AS stall, s.slug AS stall_slug,
               c.name AS category, c.slug AS category_slug
        FROM foods f
        JOIN shops s ON f.shop_id = s.id
        LEFT JOIN categories c ON f.category_id = c.id
        WHERE f.is_available = 1 AND s.is_active = 1";

$params = [];
$types = '';

if ($shop_slug !== '') { $sql .= " AND s.slug = ?"; $params[] = $shop_slug; $types .= 's'; }
if ($category !== '')  { $sql .= " AND c.slug = ?"; $params[] = $category; $types .= 's'; }
if ($search !== '')    { $sql .= " AND f.name LIKE ?"; $params[] = "%$search%"; $types .= 's'; }
if ($max_price !== null) { $sql .= " AND f.price <= ?"; $params[] = $max_price; $types .= 'd'; }

// FIX: "popular" used to be a hard filter (is_popular = 1), which returned
// zero rows whenever nothing was flagged popular yet — exactly the empty
// Popular Foods section bug. It's now a sort preference instead: popular
// items surface first, but the section still fills up to the limit with
// whatever else is available, rated highest first as a sensible fallback.
if ($popular_only) {
    $sql .= " ORDER BY f.is_popular DESC, f.avg_rating DESC, f.sort_order ASC, f.id ASC LIMIT ?";
} else {
    $sql .= " ORDER BY f.sort_order ASC, f.id ASC LIMIT ?";
}
$params[] = $limit;
$types .= 'i';

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$foods = [];
while ($row = $result->fetch_assoc()) {
    $foods[] = [
        'id'            => (int)$row['id'],
        'name'          => $row['name'],
        'price'         => (float)$row['price'],
        'image'         => $row['image'],
        'tag'           => $row['tag'],
        'is_popular'    => (bool)$row['is_popular'],
        'is_special'    => (bool)$row['is_special'],
        'avg_rating'    => (float)$row['avg_rating'],
        'rating_count'  => (int)$row['rating_count'],
        'stall'         => $row['stall'],
        'stall_slug'    => $row['stall_slug'],
        'category'      => $row['category'],
        'category_slug' => $row['category_slug'],
    ];
}

jsonResponse(true, '', ['foods' => $foods]);
?>