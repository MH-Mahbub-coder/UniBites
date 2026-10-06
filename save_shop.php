<?php
// ============================================================
//  UniBites API — Admin: create or update a shop
//  Creating a shop also creates its login account:
//    email    = <slug>@uiu.ac.bd   (slug generated from the shop name)
//    password = 12345              (default, per project spec)
//  POST JSON: { shop_id?, name, location, description, active }
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireAdmin();
$admin_id = (int)$_SESSION['user_id'];

const DEFAULT_SHOP_PASSWORD = '12345';

$data        = json_decode(file_get_contents('php://input'), true) ?? [];
$shop_id     = (int)($data['shop_id'] ?? 0);
$name        = trim($data['name'] ?? '');
$location    = trim($data['location'] ?? '');
$description = trim($data['description'] ?? '');
$active      = !empty($data['active']) ? 1 : 0;

if ($name === '') jsonResponse(false, 'Shop name is required.');

// "Khan's Kitchen" -> "khans-kitchen" (apostrophes dropped, not turned into dashes)
$slug = strtolower($name);
$slug = preg_replace("/['\x{2019}`]/u", '', $slug);
$slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
if ($slug === '') jsonResponse(false, 'Shop name must contain letters or numbers.');

$email = $slug . '@uiu.ac.bd';

// Slug and login email must be unique (ignoring this shop itself on update)
$dup = $conn->prepare("SELECT id FROM shops WHERE slug = ? AND id <> ? LIMIT 1");
$dup->bind_param('si', $slug, $shop_id);
$dup->execute();
if ($dup->get_result()->fetch_assoc()) {
    jsonResponse(false, 'Another shop already uses this name (or one that produces the same login email).');
}

$dupUser = $conn->prepare("SELECT id FROM users WHERE email = ? AND (shop_id IS NULL OR shop_id <> ?) LIMIT 1");
$dupUser->bind_param('si', $email, $shop_id);
$dupUser->execute();
if ($dupUser->get_result()->fetch_assoc()) {
    jsonResponse(false, "The login email $email is already taken by another account.");
}

$conn->begin_transaction();
try {
    if ($shop_id > 0) {
        // ---------- UPDATE ----------
        $exists = $conn->prepare("SELECT id FROM shops WHERE id = ? LIMIT 1");
        $exists->bind_param('i', $shop_id);
        $exists->execute();
        if (!$exists->get_result()->fetch_assoc()) throw new Exception('Shop not found.');

        $upd = $conn->prepare("UPDATE shops SET name = ?, slug = ?, description = ?, location = ?, is_active = ? WHERE id = ?");
        $upd->bind_param('ssssii', $name, $slug, $description, $location, $active, $shop_id);
        $upd->execute();

        // Keep the shop's login account in sync (name, email, active state)
        $updU = $conn->prepare("UPDATE users SET first_name = ?, email = ?, is_active = ? WHERE shop_id = ? AND role = 'shop'");
        $updU->bind_param('ssii', $name, $email, $active, $shop_id);
        $updU->execute();

        $conn->commit();
        jsonResponse(true, 'Shop updated successfully!', ['shop_id' => $shop_id, 'email' => $email]);
    }

    // ---------- CREATE ----------
    $sort = (int)$conn->query("SELECT COALESCE(MAX(sort_order),0) + 1 FROM shops")->fetch_row()[0];

    $ins = $conn->prepare("
        INSERT INTO shops (name, slug, description, location, is_active, created_by, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->bind_param('ssssiii', $name, $slug, $description, $location, $active, $admin_id, $sort);
    $ins->execute();
    $new_shop_id = $conn->insert_id;

    $hash = password_hash(DEFAULT_SHOP_PASSWORD, PASSWORD_BCRYPT);
    $insU = $conn->prepare("
        INSERT INTO users (first_name, email, password, role, shop_id, is_active, email_verified)
        VALUES (?, ?, ?, 'shop', ?, ?, 1)
    ");
    $insU->bind_param('sssii', $name, $email, $hash, $new_shop_id, $active);
    $insU->execute();

    $conn->commit();
    jsonResponse(true, 'Shop created successfully!', [
        'shop_id'  => $new_shop_id,
        'email'    => $email,
        'password' => DEFAULT_SHOP_PASSWORD,
        'created'  => true,
    ]);

} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(false, 'Could not save shop: ' . $e->getMessage());
}
?>