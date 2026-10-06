<?php
// ============================================================
//  UniBites API — Get Shops
//  Returns all active shops for the canteen tabs / footer links.
// ============================================================
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$result = $conn->query("
    SELECT id, name, slug, description, location, cover_image
    FROM shops
    WHERE is_active = 1
    ORDER BY sort_order ASC
");

$shops = [];
while ($row = $result->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $shops[] = $row;
}

jsonResponse(true, '', ['shops' => $shops]);
?>