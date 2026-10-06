<?php
// ============================================================
//  UniBites API — Become a rider (students opt in)
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireStudent();
$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("UPDATE users SET is_rider = 1 WHERE id = ? AND role = 'student'");
$stmt->bind_param('i', $user_id);
$stmt->execute();

jsonResponse(true, 'You are now a UniBites rider!');
?>