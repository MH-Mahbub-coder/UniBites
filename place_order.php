<?php
// ============================================================
//  UniBites API — Place Order
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireStudent();
$user_id = (int)$_SESSION['user_id'];

$data           = json_decode(file_get_contents('php://input'), true);
$full_name      = sanitize($conn, $data['full_name']      ?? '');
$phone          = sanitize($conn, $data['phone']          ?? '');
$location       = sanitize($conn, $data['location']       ?? '');
$email          = sanitize($conn, $data['email']          ?? '');
$student_id     = sanitize($conn, $data['student_id']     ?? '');
$notes          = sanitize($conn, $data['notes']          ?? '');
$payment_method = sanitize($conn, $data['payment_method'] ?? 'cash_on_delivery');
$items          = $data['items'] ?? [];

if (empty($full_name) || empty($phone) || empty($location)) {
    jsonResponse(false, 'Full name, phone and location are required.');
}
if (empty($items)) {
    jsonResponse(false, 'Your cart is empty.');
}
if (!in_array($payment_method, ['cash_on_delivery', 'online'])) {
    $payment_method = 'cash_on_delivery';
}

$byShop = [];
foreach ($items as $item) {
    $stallName = trim($item['stall'] ?? '');
    if (!isset($byShop[$stallName])) $byShop[$stallName] = [];
    $byShop[$stallName][] = $item;
}

$conn->begin_transaction();
$createdOrders = [];

try {
    foreach ($byShop as $stallName => $shopItems) {
        $shopStmt = $conn->prepare("SELECT id FROM shops WHERE name = ? LIMIT 1");
        $shopStmt->bind_param('s', $stallName);
        $shopStmt->execute();
        $shopRow = $shopStmt->get_result()->fetch_assoc();
        if (!$shopRow) continue;
        $shop_id = (int)$shopRow['id'];

        $subtotal = 0;
        foreach ($shopItems as $it) {
            $subtotal += floatval($it['price']) * intval($it['qty']);
        }
        $delivery_fee = 18.00;
        $total = $subtotal + $delivery_fee;
        $order_number = '0' . str_pad((string)mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT);

        $ord = $conn->prepare("
            INSERT INTO orders
              (user_id, shop_id, order_number, full_name, phone, location, email,
               student_id, notes, subtotal, delivery_fee, total,
               payment_method, delivery_type, status, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'delivery', 'pending', 'pending')
        ");
        // FIX: 13 bound values, 3 of them doubles (subtotal, delivery_fee,
        // total) — the type string must have exactly 13 characters with
        // THREE 'd's. Previous version was 12 chars with only two 'd's,
        // which made mysqli throw a fatal error, which broke the JSON
        // response, which is what produced "Network error" on the frontend.
        $ord->bind_param(
            'iisssssssddds',
            $user_id, $shop_id, $order_number, $full_name, $phone, $location,
            $email, $student_id, $notes, $subtotal, $delivery_fee, $total, $payment_method
        );
        $ord->execute();
        $order_id = $conn->insert_id;

        $itemStmt = $conn->prepare("
            INSERT INTO order_items (order_id, food_id, food_name, quantity, unit_price, total_price)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $findFood = $conn->prepare("SELECT id FROM foods WHERE shop_id = ? AND name = ? LIMIT 1");

        foreach ($shopItems as $it) {
            $name  = sanitize($conn, $it['name']);
            $qty   = (int)$it['qty'];
            $price = (float)$it['price'];
            $lineTotal = $price * $qty;

            $findFood->bind_param('is', $shop_id, $name);
            $findFood->execute();
            $foodRow = $findFood->get_result()->fetch_assoc();
            $food_id = $foodRow ? (int)$foodRow['id'] : null;

            $itemStmt->bind_param('iisidd', $order_id, $food_id, $name, $qty, $price, $lineTotal);
            $itemStmt->execute();
        }

        $rider_earn = round($delivery_fee * 0.6, 2);
        $delStmt = $conn->prepare("
            INSERT INTO deliveries (order_id, delivery_fee, rider_earn, status)
            VALUES (?, ?, ?, 'pending')
        ");
        $delStmt->bind_param('idd', $order_id, $delivery_fee, $rider_earn);
        $delStmt->execute();

        $payStmt = $conn->prepare("
            INSERT INTO payments (order_id, user_id, method, amount, currency, status)
            VALUES (?, ?, ?, ?, 'BDT', 'pending')
        ");
        $payStmt->bind_param('iisd', $order_id, $user_id, $payment_method, $total);
        $payStmt->execute();

        $logStmt = $conn->prepare("
            INSERT INTO order_status_log (order_id, status, note, changed_by)
            VALUES (?, 'pending', 'Order placed by customer', ?)
        ");
        $logStmt->bind_param('ii', $order_id, $user_id);
        $logStmt->execute();

        $notif = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, ?, ?, 'order_confirmed')
        ");
        $notifTitle = "Order #$order_number Placed!";
        $notifMsg   = "Your order from $stallName has been placed. Total: BDT $total";
        $notif->bind_param('iss', $user_id, $notifTitle, $notifMsg);
        $notif->execute();

        $createdOrders[] = ['order_id' => $order_id, 'order_number' => $order_number, 'shop' => $stallName, 'total' => $total];
    }

    if (empty($createdOrders)) {
        throw new Exception('None of the items matched a known shop.');
    }

    $conn->commit();
    jsonResponse(true, 'Order placed successfully!', ['orders' => $createdOrders]);

} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(false, 'Order failed: ' . $e->getMessage());
}
?>