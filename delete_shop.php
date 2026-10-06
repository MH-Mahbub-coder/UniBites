<?php
// ============================================================
//  UniBites API — Admin: delete a shop
//  A shop that has order history can't be deleted (that would erase
//  customers' and riders' records). Deactivate it instead.
//  POST JSON: { shop_id }
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireAdmin();

$data    = json_decode(file_get_contents('php://input'), true) ?? [];
$shop_id = (int)($data['shop_id'] ?? 0);
if (!$shop_id) jsonResponse(false, 'Shop ID is required.');

$q = $conn->prepare("SELECT COUNT(*) FROM orders WHERE shop_id = ?");
$q->bind_param('i', $shop_id);
$q->execute();
$orderCount = (int)$q->get_result()->fetch_row()[0];

if ($orderCount > 0) {
    jsonResponse(false, "This shop has $orderCount order(s) on record, so it can't be deleted. Set it to Inactive instead — it will disappear from the student side and its login will be disabled.");
}

// foods and the shop's login account are removed automatically (ON DELETE CASCADE)
$del = $conn->prepare("DELETE FROM shops WHERE id = ?");
$del->bind_param('i', $shop_id);
$del->execute();

if ($del->affected_rows === 0) jsonResponse(false, 'Shop not found.');
jsonResponse(true, 'Shop deleted.');
?>