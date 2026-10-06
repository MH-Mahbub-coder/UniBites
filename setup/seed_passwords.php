<?php
// ============================================================
//  UniBites — One-time setup script
//  Run this ONCE after importing the SQL files, to set real
//  bcrypt password hashes for all demo accounts (default: 12345)
//  Run via terminal:  php setup/seed_passwords.php
//  Or in browser:     http://localhost/unibites/setup/seed_passwords.php
// ============================================================
$host = 'localhost';
$user = 'root';
$pass = 'shakib';           // <-- put your MySQL root password here if you have one
$db   = 'unibites';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$defaultPassword = '12345';
$hash = password_hash($defaultPassword, PASSWORD_BCRYPT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE password = 'PENDING_HASH'");
$stmt->bind_param('s', $hash);
$stmt->execute();

echo "✅ Updated {$stmt->affected_rows} account(s) with password: $defaultPassword\n";
echo "Hash used: $hash\n";
echo "You can now delete this file or leave it — it only updates rows still marked PENDING_HASH.\n";

$conn->close();
?>
