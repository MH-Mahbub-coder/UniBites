<?php
// ============================================================
//  UniBites API — Delete a Food Item
//  POST JSON: { item_id }
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$shop_id = requireShop();

$data    = json_decode(file_get_contents('php://input'), true);
$item_id = (int)($data['item_id'] ?? 0);

if (!$item_id) jsonResponse(false, 'Item ID is required.');

$check = $conn->prepare("SELECT id FROM foods WHERE id = ? AND shop_id = ? LIMIT 1");
$check->bind_param('ii', $item_id, $shop_id);
$check->execute();
if (!$check->get_result()->fetch_assoc()) {
    jsonResponse(false, 'Item not found or does not belong to your shop.');
}

$del = $conn->prepare("DELETE FROM foods WHERE id = ? AND shop_id = ?");
$del->bind_param('ii', $item_id, $shop_id);
$del->execute();

jsonResponse(true, 'Item deleted.');
?>