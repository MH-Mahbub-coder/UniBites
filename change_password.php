<?php
// ============================================================
//  UniBites API — Change own password
//  POST JSON: { current_password, new_password }
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireLogin();
$user_id = (int)$_SESSION['user_id'];

$data    = json_decode(file_get_contents('php://input'), true) ?? [];
$current = trim($data['current_password'] ?? '');
$new     = trim($data['new_password'] ?? '');

if (strlen($new) < 6) jsonResponse(false, 'New password must be at least 6 characters.');

$stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row || !password_verify($current, $row['password'])) {
    jsonResponse(false, 'Current password is incorrect.');
}

$hash = password_hash($new, PASSWORD_BCRYPT);
$upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$upd->bind_param('si', $hash, $user_id);
$upd->execute();

jsonResponse(true, 'Password updated successfully!');
?>