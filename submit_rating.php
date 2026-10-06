<?php
// ============================================================
//  UniBites API — Submit ratings for a delivered order
//  A student rates once per order: the rider (if there was one) and
//  each food item. Skipped items (rating 0) simply aren't inserted.
//  POST JSON: {
//    order_id,
//    rider_rating (0-5, 0 = skipped), rider_review (optional),
//    food_ratings: [{ food_id, rating (0-5), review (optional) }]
//  }
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireStudent();
$user_id = (int)$_SESSION['user_id'];

$data         = json_decode(file_get_contents('php://input'), true) ?? [];
$order_id     = (int)($data['order_id'] ?? 0);
$rider_rating = (int)($data['rider_rating'] ?? 0);
$rider_review = trim($data['rider_review'] ?? '');
$foodRatings  = is_array($data['food_ratings'] ?? null) ? $data['food_ratings'] : [];

if (!$order_id) jsonResponse(false, 'Order ID is required.');
if ($rider_rating < 0 || $rider_rating > 5) $rider_rating = 0;

$stmt = $conn->prepare("
    SELECT o.id, o.user_id, o.status, o.shop_id, o.rider_id, d.id AS delivery_id
    FROM orders o
    LEFT JOIN deliveries d ON d.order_id = o.id
    WHERE o.id = ? LIMIT 1
");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) jsonResponse(false, 'Order not found.');
if ((int)$order['user_id'] !== $user_id) jsonResponse(false, 'This is not your order.');
if (!in_array($order['status'], ['delivered', 'completed'], true)) {
    jsonResponse(false, 'You can only rate an order after it has been delivered.');
}

// One rating pass per order — if any food on this order already has a
// review, the whole order counts as rated.
$check = $conn->prepare("SELECT id FROM reviews WHERE order_id = ? LIMIT 1");
$check->bind_param('i', $order_id);
$check->execute();
if ($check->get_result()->fetch_assoc()) {
    jsonResponse(false, 'You have already rated this order.');
}

// Which food_ids actually belong to this order — a rating for anything
// else is ignored rather than trusted blindly from the request
$validFoods = [];
$itemsStmt = $conn->prepare("SELECT food_id FROM order_items WHERE order_id = ? AND food_id IS NOT NULL");
$itemsStmt->bind_param('i', $order_id);
$itemsStmt->execute();
foreach ($itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $validFoods[(int)$r['food_id']] = true;
}

$conn->begin_transaction();
try {
    $foodCount = 0;
    $insFood = $conn->prepare("
        INSERT INTO reviews (user_id, food_id, order_id, shop_id, rating, review, is_visible)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    foreach ($foodRatings as $fr) {
        $food_id = (int)($fr['food_id'] ?? 0);
        $rating  = (int)($fr['rating'] ?? 0);
        $review  = trim($fr['review'] ?? '');
        if ($rating < 1 || $rating > 5) continue;          // 0 or invalid = skipped
        if (!isset($validFoods[$food_id])) continue;       // not part of this order

        $insFood->bind_param('iiiiss', $user_id, $food_id, $order_id, $order['shop_id'], $rating, $review);
        $insFood->execute();
        $foodCount++;
    }

    $riderCounted = false;
    if ($order['rider_id'] && $order['delivery_id'] && $rider_rating >= 1) {
        $insRider = $conn->prepare("
            INSERT INTO rider_ratings (delivery_id, rider_id, customer_id, rating, comment)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insRider->bind_param('iiiis', $order['delivery_id'], $order['rider_id'], $user_id, $rider_rating, $rider_review);
        $insRider->execute();
        $riderCounted = true;
    }

    if ($foodCount === 0 && !$riderCounted) {
        throw new Exception('Please rate at least one thing before submitting.');
    }

    $conn->commit();
    jsonResponse(true, 'Thanks! Your ratings have been submitted.');
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(false, 'Could not submit ratings: ' . $e->getMessage());
}
?>