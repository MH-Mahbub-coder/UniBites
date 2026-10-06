<?php
// ============================================================
//  UniBites API — Save (Create/Update) a Food Item
//  multipart/form-data POST fields:
//    item_id (optional — present = update, absent = create)
//    name, price, category, description, available (0/1)
//    image (file, optional)
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$shop_id = requireShop();

$item_id     = (int)($_POST['item_id'] ?? 0);
$name        = sanitize($conn, $_POST['name'] ?? '');
$price       = (float)($_POST['price'] ?? 0);
$categoryName= sanitize($conn, $_POST['category'] ?? '');
$description = sanitize($conn, $_POST['description'] ?? '');
$available   = isset($_POST['available']) && $_POST['available'] == '1' ? 1 : 0;

if (empty($name) || $price <= 0) {
    jsonResponse(false, 'Name and a valid price are required.');
}

// Resolve category name -> category_id
$category_id = null;
if ($categoryName !== '') {
    $catStmt = $conn->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
    $catStmt->bind_param('s', $categoryName);
    $catStmt->execute();
    $catRow = $catStmt->get_result()->fetch_assoc();
    if ($catRow) $category_id = (int)$catRow['id'];
}

// Handle image upload (optional — keeps existing image if none provided)
$imageFilename = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    $fileType = mime_content_type($_FILES['image']['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        jsonResponse(false, 'Only JPG, PNG, or WebP images are allowed.');
    }
    if ($_FILES['image']['size'] > $maxSize) {
        jsonResponse(false, 'Image must be under 2MB.');
    }

    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $imageFilename = 'food_' . $shop_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
    $destPath = __DIR__ . '/uploads/foods/' . $imageFilename;

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
        jsonResponse(false, 'Failed to save the uploaded image.');
    }
}

if ($item_id > 0) {
    // ---- UPDATE existing item (must belong to this shop) ----
    $check = $conn->prepare("SELECT id, image FROM foods WHERE id = ? AND shop_id = ? LIMIT 1");
    $check->bind_param('ii', $item_id, $shop_id);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    if (!$existing) jsonResponse(false, 'Item not found or does not belong to your shop.');

    $finalImage = $imageFilename ?? $existing['image']; // keep old image if none uploaded

    $upd = $conn->prepare("
        UPDATE foods SET name = ?, price = ?, category_id = ?, description = ?, image = ?, is_available = ?
        WHERE id = ? AND shop_id = ?
    ");
    $upd->bind_param('sdissiii', $name, $price, $category_id, $description, $finalImage, $available, $item_id, $shop_id);
    $upd->execute();

    jsonResponse(true, 'Item updated successfully!', ['item_id' => $item_id]);

} else {
    // ---- CREATE new item ----
    $finalImage = $imageFilename ?? 'food_default.jpg';

    $ins = $conn->prepare("
        INSERT INTO foods (shop_id, category_id, name, description, price, image, is_available)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->bind_param('iissdsi', $shop_id, $category_id, $name, $description, $price, $finalImage, $available);
    $ins->execute();

    jsonResponse(true, 'Item added successfully!', ['item_id' => $conn->insert_id]);
}
?>