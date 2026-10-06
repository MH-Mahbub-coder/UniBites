<?php
// ============================================================
//  UniBites API — Register (Students only)
//  Shops and admin accounts are created by the admin panel, never
//  self-registered here.
//  POST JSON: { first_name, last_name, email, password }
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

$data       = json_decode(file_get_contents('php://input'), true);
$first_name = sanitize($conn, $data['first_name'] ?? '');
$last_name  = sanitize($conn, $data['last_name']  ?? '');
$email      = sanitize($conn, strtolower($data['email'] ?? ''));
$password   = trim($data['password'] ?? '');

if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
    jsonResponse(false, 'All fields are required.');
}

// SERVER-SIDE mirror of the frontend regex: <name>@<dept>.uiu.ac.bd
// Never trust client-side validation alone — this is the real gate.
if (!preg_match('/^[a-zA-Z][a-zA-Z0-9._]*@[a-zA-Z][a-zA-Z0-9-]*\.uiu\.ac\.bd$/', $email)) {
    jsonResponse(false, 'Please use a valid university email (name@department.uiu.ac.bd).');
}

if (strlen($password) < 6) {
    jsonResponse(false, 'Password must be at least 6 characters.');
}

// Extract department from email (the part between @ and .uiu.ac.bd)
preg_match('/@([a-zA-Z0-9-]+)\.uiu\.ac\.bd$/', $email, $matches);
$department = strtoupper($matches[1] ?? '');

// Check for existing account
$check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$check->bind_param('s', $email);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    jsonResponse(false, 'This email is already registered.');
}

$hashed = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare("
    INSERT INTO users (first_name, last_name, email, password, department, role, email_verified, is_active)
    VALUES (?, ?, ?, ?, ?, 'student', 1, 1)
");
$stmt->bind_param('sssss', $first_name, $last_name, $email, $hashed, $department);

if (!$stmt->execute()) {
    jsonResponse(false, 'Registration failed. Please try again.');
}

$user_id = $conn->insert_id;

// Auto login after successful registration
$_SESSION['user_id']    = $user_id;
$_SESSION['user_name']  = $first_name . ' ' . $last_name;
$_SESSION['user_email'] = $email;
$_SESSION['user_role']  = 'student';

jsonResponse(true, 'Account created successfully.', [
    'user' => [
        'id'    => $user_id,
        'name'  => $first_name . ' ' . $last_name,
        'email' => $email,
        'role'  => 'student',
    ]
]);
?>