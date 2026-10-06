<?php
// ============================================================
//  UniBites API — Top riders leaderboard
// ============================================================
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$rows = $conn->query("
    SELECT u.id, u.first_name, u.last_name, u.department, u.avg_rating, u.rating_count,
           (SELECT COUNT(*) FROM deliveries WHERE rider_id = u.id AND status = 'delivered') AS deliveries
    FROM users u
    WHERE u.role = 'student' AND u.is_rider = 1 AND u.is_active = 1
    HAVING deliveries > 0
    ORDER BY deliveries DESC
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

$riders = array_map(fn($r) => [
    'id' => (int)$r['id'],
    'name' => trim($r['first_name'] . ' ' . $r['last_name']),
    'department' => $r['department'] ?? 'UIU',
    'avg_rating' => (float)$r['avg_rating'],
    'rating_count' => (int)$r['rating_count'],
    'deliveries' => (int)$r['deliveries'],
], $rows);

jsonResponse(true, '', ['riders' => $riders]);
?>