<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$members      = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1")->fetch_row()[0];
$activeRiders = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_rider = 1 AND is_active = 1")->fetch_row()[0];
$dedicated    = (int)$conn->query("
    SELECT COUNT(*) FROM (
        SELECT rider_id FROM deliveries WHERE status = 'delivered'
        GROUP BY rider_id HAVING COUNT(*) >= 10
    ) t
")->fetch_row()[0];

jsonResponse(true, '', ['members' => $members, 'active_riders' => $activeRiders, 'dedicated_riders' => $dedicated]);
?>