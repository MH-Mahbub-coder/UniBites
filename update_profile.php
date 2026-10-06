<?php
// ============================================================
//  UniBites API — Update own profile
//  POST JSON: { phone, default_location, university_id }
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireStudent();
$user_id = (int)$_SESSION['user_id'];

$data     = json_decode(file_get_contents('php://input'), true) ?? [];
$phone    = trim($data['phone'] ?? '');
$location = trim($data['default_location'] ?? '');
$uid      = trim($data['university_id'] ?? '');

$stmt = $conn->prepare("UPDATE users SET phone = ?, default_location = ?, university_id = ? WHERE id = ?");
$stmt->bind_param('sssi', $phone, $location, $uid, $user_id);
$stmt->execute();

jsonResponse(true, 'Profile updated successfully!');
?>