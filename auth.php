<?php
// ============================================================
//  UniBites — Auth / Session Helper
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// A rider can hold at most this many unfinished deliveries at once,
// so nobody can grab every order and sit on them.
const MAX_ACTIVE_DELIVERIES = 3;

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUser($conn) {
    if (!isLoggedIn()) return null;
    $id = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_assoc() : null;
}

// Any logged-in, still-active user. Re-checks is_active on every request,
// so suspending an account takes effect immediately.
function requireLogin() {
    global $conn;
    if (!isLoggedIn()) {
        jsonResponse(false, 'Please log in first.', ['code' => 'login_required']);
    }
    $id = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT is_active FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row || !(int)$row['is_active']) {
        session_unset();
        session_destroy();
        jsonResponse(false, 'Your account is no longer active.', ['code' => 'inactive']);
    }
}

function requireStudent() {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'student') {
        jsonResponse(false, 'This action requires a student account.', ['code' => 'wrong_role']);
    }
}

// A student who has opted in as a rider. Returns the rider's user id.
function requireRider() {
    global $conn;
    requireStudent();
    $id = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT is_rider FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row || !(int)$row['is_rider']) {
        jsonResponse(false, 'You need to become a rider first.', ['code' => 'not_rider']);
    }
    return $id;
}

function requireShop() {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'shop') {
        jsonResponse(false, 'This action requires a shop account.', ['code' => 'wrong_role']);
    }
    return (int)($_SESSION['shop_id'] ?? 0);
}

function requireAdmin() {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        jsonResponse(false, 'This action requires an admin account.', ['code' => 'wrong_role']);
    }
}
?>