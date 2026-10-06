<?php
// ============================================================
//  UniBites API — Login
//  Works for all three roles: student, shop, admin.
//  Frontend decides where to redirect based on the returned role.
//  POST JSON: { email, password }
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

$data     = json_decode(file_get_contents('php://input'), true);
$email    = sanitize($conn, strtolower($data['email'] ?? ''));
$password = trim($data['password'] ?? '');

if (empty($email) || empty($password)) {
    jsonResponse(false, 'Email and password are required.');
}

$stmt = $conn->prepare("
    SELECT u.*, s.name AS shop_name, s.slug AS shop_slug
    FROM users u
    LEFT JOIN shops s ON u.shop_id = s.id
    WHERE u.email = ? AND u.is_active = 1
    LIMIT 1
");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !password_verify($password, $user['password'])) {
    jsonResponse(false, 'Invalid email or password.');
}

// Set session — shape depends on role
$_SESSION['user_id']    = $user['id'];
$_SESSION['user_name']  = trim($user['first_name'] . ' ' . ($user['last_name'] ?? ''));
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role']  = $user['role'];

$responseUser = [
    'id'    => $user['id'],
    'name'  => $_SESSION['user_name'],
    'email' => $user['email'],
    'role'  => $user['role'],
];

if ($user['role'] === 'shop') {
    $_SESSION['shop_id'] = (int)$user['shop_id'];
    $responseUser['shop_id']   = (int)$user['shop_id'];
    $responseUser['shop_name'] = $user['shop_name'];
    $responseUser['shop_slug'] = $user['shop_slug'];
}

if ($user['role'] === 'student') {
    $responseUser['is_rider']       = (bool)$user['is_rider'];
    $responseUser['balance']        = (float)$user['balance'];
    $responseUser['total_earnings'] = (float)$user['total_earnings'];
}

jsonResponse(true, 'Login successful.', ['user' => $responseUser]);
?>