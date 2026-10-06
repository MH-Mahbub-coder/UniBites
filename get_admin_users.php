<?php
// ============================================================
//  UniBites API — Admin: list all student accounts
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireAdmin();

$rows = $conn->query("
    SELECT id, first_name, last_name, email, department, is_rider,
           balance, total_earnings, is_active
    FROM users
    WHERE role = 'student'
    ORDER BY created_at DESC, id DESC
")->fetch_all(MYSQLI_ASSOC);

$users = array_map(function ($r) {
    return [
        'id'       => (int)$r['id'],
        'name'     => trim($r['first_name'] . ' ' . $r['last_name']),
        'email'    => $r['email'],
        'dept'     => $r['department'] ?? '—',
        'isRider'  => (bool)$r['is_rider'],
        'balance'  => (float)$r['balance'],
        'earnings' => (float)$r['total_earnings'],
        'active'   => (bool)$r['is_active'],
    ];
}, $rows);

jsonResponse(true, '', ['users' => $users]);
?>