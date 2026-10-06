<?php
// ============================================================
//  UniBites API — Admin: delete a student account
//  Students with order history can't be deleted (it would erase
//  shop and rider records). Suspend them instead.
//  POST JSON: { user_id }
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireAdmin();

$data    = json_decode(file_get_contents('php://input'), true) ?? [];
$user_id = (int)($data['user_id'] ?? 0);
if (!$user_id) jsonResponse(false, 'User ID is required.');

$stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'student' LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) jsonResponse(false, 'Student not found.');

$q = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$q->bind_param('i', $user_id);
$q->execute();
$orderCount = (int)$q->get_result()->fetch_row()[0];

if ($orderCount > 0) {
    jsonResponse(false, "This student has $orderCount order(s) on record, so the account can't be deleted. Suspend it instead — they won't be able to log in.");
}

$del = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
$del->bind_param('i', $user_id);
$del->execute();

jsonResponse(true, 'Student account deleted.');
?>