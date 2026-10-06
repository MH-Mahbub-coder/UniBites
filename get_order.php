<?php
// ============================================================
//  UniBites API — Get Order (for order tracking)
//  GET ?order_number=<num>  (student-facing, matches what customers
//                            actually see and type in)
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireLogin();

$order_number = sanitize($conn, $_GET['order_number'] ?? '');
if (empty($order_number)) {
    jsonResponse(false, 'Order number is required.');
}

$stmt = $conn->prepare("
    SELECT o.*, s.name AS shop_name,
           r.first_name AS rider_first, r.last_name AS rider_last,
           r.avg_rating AS rider_avg_rating, r.rating_count AS rider_rating_count
    FROM orders o
    JOIN shops s ON o.shop_id = s.id
    LEFT JOIN users r ON o.rider_id = r.id
    WHERE o.order_number = ?
    LIMIT 1
");
$stmt->bind_param('s', $order_number);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    jsonResponse(false, 'Order not found.');
}

// Only the order's own customer or an admin may view it
$isOwner = ($order['user_id'] == $_SESSION['user_id']);
$isAdmin = ($_SESSION['user_role'] === 'admin');
if (!$isOwner && !$isAdmin) {
    jsonResponse(false, 'You do not have permission to view this order.');
}

$itemsStmt = $conn->prepare("SELECT food_name, quantity, unit_price, total_price FROM order_items WHERE order_id = ?");
$itemsStmt->bind_param('i', $order['id']);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$logStmt = $conn->prepare("SELECT status, note, created_at FROM order_status_log WHERE order_id = ? ORDER BY created_at ASC");
$logStmt->bind_param('i', $order['id']);
$logStmt->execute();
$statusLog = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);

jsonResponse(true, '', [
    'order' => [
        'id'             => (int)$order['id'],
        'order_number'   => $order['order_number'],
        'shop'           => $order['shop_name'],
        'status'         => $order['status'],
        'delivery_type'  => $order['delivery_type'],
        'location'       => $order['location'],
        'subtotal'       => (float)$order['subtotal'],
        'delivery_fee'   => (float)$order['delivery_fee'],
        'total'          => (float)$order['total'],
        'payment_method' => $order['payment_method'],
        'payment_status' => $order['payment_status'],
        'created_at'     => $order['created_at'],
        'can_cancel'     => in_array($order['status'], ['pending', 'confirmed']),
        'rider'          => $order['rider_id'] ? [
            'name'          => trim($order['rider_first'] . ' ' . $order['rider_last']),
            'avg_rating'    => (float)$order['rider_avg_rating'],
            'rating_count'  => (int)$order['rider_rating_count'],
        ] : null,
    ],
    'items'      => $items,
    'status_log' => $statusLog,
]);
?>