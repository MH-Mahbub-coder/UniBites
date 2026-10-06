<?php
// ============================================================
//  UniBites — Database Connection
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'shakib');           // <-- your MySQL root password, if you have one
define('DB_NAME', 'unibites');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

// FIX: only trims. Every query in this project uses prepared statements,
// which already protect against SQL injection. The old version also ran
// real_escape_string(), so values were escaped twice ("Khan's" became
// "Khan\'s") and lookups like WHERE name = ? silently failed.
function sanitize($conn, $data) {
    return trim((string)($data ?? ''));
}

function jsonResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}
?>