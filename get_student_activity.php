<?php
// ============================================================
//  UniBites API — Everything a student has done: their own orders
//  AND, if they're a rider, their deliveries — merged into one feed.
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireStudent();
$user_id = (int)$_SESSION['user_id'];

$stageLabel = [
    'pending' => 'Preparing', 'confirmed' => 'Preparing', 'preparing' => 'Preparing',
    'ready' => 'Ready', 'rider_accepted' => 'Rider Assigned',
    'picked_up' => 'Out for Delivery', 'out_for_delivery' => 'Out for Delivery',
];

// ---- Profile header info ----
$uStmt = $conn->prepare("SELECT first_name, last_name, email, phone, department, university_id, default_location, balance, is_rider, avg_rating, rating_count, created_at FROM users WHERE id = ? LIMIT 1");
$uStmt->bind_param('i', $user_id);
$uStmt->execute();
$u = $uStmt->get_result()->fetch_assoc();

// ---- This student's own orders ----
$ordStmt = $conn->prepare("
    SELECT o.id, o.order_number, o.status, o.subtotal, o.delivery_fee, o.total, o.created_at,
           s.name AS shop_name,
           CONCAT(r.first_name, ' ', COALESCE(r.last_name, '')) AS rider_name,
           (SELECT COUNT(*) FROM reviews rv WHERE rv.order_id = o.id) AS already_rated
    FROM orders o
    JOIN shops s ON o.shop_id = s.id
    LEFT JOIN users r ON o.rider_id = r.id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
    LIMIT 30
");
$ordStmt->bind_param('i', $user_id);
$ordStmt->execute();
$customerOrders = $ordStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ---- This student's deliveries (only meaningful if is_rider) ----
$delStmt = $conn->prepare("
    SELECT o.id, o.order_number, o.location, o.created_at,
           d.status AS d_status, d.rider_earn,
           s.name AS shop_name,
           rr.rating AS order_rating
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    JOIN shops s ON o.shop_id = s.id
    LEFT JOIN rider_ratings rr ON rr.delivery_id = d.id
    WHERE d.rider_id = ? AND d.status <> 'cancelled'
    ORDER BY o.created_at DESC
    LIMIT 30
");
$delStmt->bind_param('i', $user_id);
$delStmt->execute();
$riderDeliveries = $delStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ---- Items for every order involved (one batch query) ----
$allOrderIds = array_unique(array_merge(
    array_column($customerOrders, 'id'),
    array_column($riderDeliveries, 'id')
));
$itemsByOrder = [];
if ($allOrderIds) {
    $placeholders = implode(',', array_fill(0, count($allOrderIds), '?'));
    $types = str_repeat('i', count($allOrderIds));
    $stmt = $conn->prepare("
        SELECT oi.order_id, oi.food_id, oi.food_name, oi.quantity, oi.total_price, f.image
        FROM order_items oi LEFT JOIN foods f ON oi.food_id = f.id
        WHERE oi.order_id IN ($placeholders)
    ");
    $stmt->bind_param($types, ...$allOrderIds);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $oid = (int)$row['order_id'];
        $itemsByOrder[$oid][] = [
            'food_id' => $row['food_id'] !== null ? (int)$row['food_id'] : null,
            'food_name' => $row['food_name'], 'quantity' => (int)$row['quantity'],
            'total_price' => (float)$row['total_price'], 'image' => $row['image'],
        ];
    }
}

$activities = [];

foreach ($customerOrders as $o) {
    $isDone = in_array($o['status'], ['delivered', 'completed'], true);
    $activities[] = [
        'type'          => $isDone ? 'customer_completed' : 'customer_pending',
        'id'            => (int)$o['id'],
        'order_number'  => $o['order_number'],
        'timestamp'     => $o['created_at'],
        'items'         => $itemsByOrder[(int)$o['id']] ?? [],
        'shop'          => $o['shop_name'],
        'rider'         => $o['rider_name'] ? trim($o['rider_name']) : null,
        'subtotal'      => (float)$o['subtotal'],
        'delivery_fee'  => (float)$o['delivery_fee'],
        'total'         => (float)$o['total'],
        'stage'         => $stageLabel[$o['status']] ?? ucfirst($o['status']),
        'already_rated' => (int)$o['already_rated'] > 0,
        'is_cancelled'  => $o['status'] === 'cancelled',
    ];
}

foreach ($riderDeliveries as $d) {
    $activities[] = [
        'type'          => $d['d_status'] === 'delivered' ? 'rider_delivered' : 'rider_progress',
        'id'            => (int)$d['id'],
        'order_number'  => $d['order_number'],
        'timestamp'     => $d['created_at'],
        'items'         => $itemsByOrder[(int)$d['id']] ?? [],
        'shop'          => $d['shop_name'],
        'location'      => $d['location'],
        'earn'          => (float)$d['rider_earn'],
        'order_rating'  => $d['order_rating'] !== null ? (int)$d['order_rating'] : null,
    ];
}

usort($activities, fn($a, $b) => strtotime($b['timestamp']) <=> strtotime($a['timestamp']));

jsonResponse(true, '', [
    'profile' => [
        'name'             => trim($u['first_name'] . ' ' . ($u['last_name'] ?? '')),
        'email'            => $u['email'],
        'phone'            => $u['phone'],
        'department'       => $u['department'],
        'university_id'    => $u['university_id'],
        'default_location' => $u['default_location'],
        'balance'          => (float)$u['balance'],
        'is_rider'         => (bool)$u['is_rider'],
        'avg_rating'       => (float)$u['avg_rating'],
        'rating_count'     => (int)$u['rating_count'],
        'joined'           => date('F j, Y', strtotime($u['created_at'])),
    ],
    'activities' => $activities,
]);
?>