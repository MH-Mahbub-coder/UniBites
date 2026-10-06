<?php
// ============================================================
//  UniBites API — Admin: suspend / reactivate a student
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

$stmt = $conn->prepare("SELECT is_active FROM users WHERE id = ? AND role = 'student' LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) jsonResponse(false, 'Student not found.');

$newState = ((int)$user['is_active'] === 1) ? 0 : 1;
$upd = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'student'");
$upd->bind_param('ii', $newState, $user_id);
$upd->execute();

jsonResponse(true, $newState ? 'Account reactivated.' : 'Account suspended.', ['active' => (bool)$newState]);
?>