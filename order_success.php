<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireLogin();

$order_id = (int)($_GET['order_id'] ?? 0);
$txn      = htmlspecialchars($_GET['txn'] ?? '');
$user_id  = (int)$_SESSION['user_id'];

// Fetch order from DB
$stmt = $conn->prepare("
    SELECT o.*, u.first_name, u.last_name
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.id = ? AND o.user_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header('Location:/index.php');
    exit;
}

// Fetch order items
$items_stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items_stmt->bind_param('i', $order_id);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UniBites - Order Confirmed!</title>
  <link rel="stylesheet" href="/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .success-page { max-width: 680px; margin: 0 auto; padding: 60px 24px; text-align: center; }
    .success-icon {
      width: 90px; height: 90px; border-radius: 50%;
      background: rgba(40,167,69,0.15); border: 3px solid #28a745;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 24px; font-size: 2.5rem; color: #28a745;
      animation: popIn 0.5s ease;
    }
    @keyframes popIn {
      from { transform: scale(0); opacity:0; }
      to   { transform: scale(1); opacity:1; }
    }
    .success-title { font-size: 2rem; font-weight: 800; margin-bottom: 8px; }
    .success-sub   { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 32px; }

    .order-summary-card {
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: var(--radius); padding: 28px; text-align: left; margin-bottom: 24px;
    }
    .os-title { font-size: 0.85rem; font-weight: 700; color: var(--primary); text-transform: uppercase; margin-bottom: 18px; }
    .os-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 0.85rem; }
    .os-row:last-child { border-bottom: none; }
    .os-row span:first-child { color: var(--text-muted); }
    .os-row span:last-child  { font-weight: 600; color: var(--text-white); }
    .os-row.highlight span:last-child { color: var(--primary); font-size: 1rem; font-weight: 800; }

    .status-pill {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(255,193,7,0.15); color: #FFC107;
      border: 1px solid rgba(255,193,7,0.4);
      font-size: 0.78rem; font-weight: 700; padding: 5px 14px; border-radius: 20px;
    }
    .paid-pill {
      background: rgba(40,167,69,0.15); color: #28a745;
      border-color: rgba(40,167,69,0.4);
    }

    .items-list { margin-top: 14px; }
    .item-line { display: flex; justify-content: space-between; font-size: 0.82rem; color: var(--text-gray); padding: 5px 0; }
    .item-line span:last-child { color: var(--text-white); font-weight: 600; }

    .action-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
  </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>

<div class="success-page">
  <div class="success-icon"><i class="fas fa-check"></i></div>

  <h1 class="success-title">
    <?= ($order['payment_status'] === 'paid') ? 'Payment Successful!' : 'Order Placed!' ?>
  </h1>
  <p class="success-sub">
    <?php if ($order['payment_method'] === 'online' && $order['payment_status'] === 'paid'): ?>
      Your payment was processed successfully and your order is confirmed.
    <?php elseif ($order['delivery_type'] === 'pickup'): ?>
      Your order is confirmed! Visit the stall when it's ready for pickup.
    <?php else: ?>
      Your order is confirmed! A campus rider will deliver your food shortly.
    <?php endif; ?>
  </p>

  <!-- Order Summary Card -->
  <div class="order-summary-card">
    <div class="os-title">Order Summary</div>

    <div class="os-row">
      <span>Order Number</span>
      <span style="color:var(--primary);">#<?= htmlspecialchars($order['order_number']) ?></span>
    </div>
    <div class="os-row">
      <span>Customer</span>
      <span><?= htmlspecialchars($order['full_name']) ?></span>
    </div>
    <div class="os-row">
      <span>Delivery To</span>
      <span><?= htmlspecialchars($order['location']) ?></span>
    </div>
    <div class="os-row">
      <span>Order Type</span>
      <span><?= ucfirst($order['delivery_type']) ?></span>
    </div>
    <div class="os-row">
      <span>Payment Method</span>
      <span><?= ($order['payment_method'] === 'cash_on_delivery') ? 'Cash on Delivery' : 'Online Payment' ?></span>
    </div>
    <?php if ($txn): ?>
    <div class="os-row">
      <span>Transaction ID</span>
      <span style="font-size:0.78rem;"><?= $txn ?></span>
    </div>
    <?php endif; ?>
    <div class="os-row">
      <span>Payment Status</span>
      <span>
        <span class="status-pill <?= ($order['payment_status'] === 'paid') ? 'paid-pill' : '' ?>">
          <i class="fas fa-<?= ($order['payment_status'] === 'paid') ? 'check-circle' : 'clock' ?>"></i>
          <?= ucfirst($order['payment_status']) ?>
        </span>
      </span>
    </div>

    <!-- Items -->
    <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border);">
      <div style="font-size:0.78rem;font-weight:700;color:var(--text-muted);margin-bottom:10px;text-transform:uppercase;">Items Ordered</div>
      <div class="items-list">
        <?php foreach ($items as $item): ?>
        <div class="item-line">
          <span><?= (int)$item['quantity'] ?>× <?= htmlspecialchars($item['food_name']) ?> <span style="color:var(--primary);font-size:0.72rem;">(<?= htmlspecialchars($item['stall_name']) ?>)</span></span>
          <span>BDT <?= number_format($item['total_price'], 2) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);">
      <div class="os-row"><span>Subtotal</span><span>BDT <?= number_format($order['subtotal'], 2) ?></span></div>
      <div class="os-row"><span>Delivery Fee</span><span>BDT <?= number_format($order['delivery_fee'], 2) ?></span></div>
      <div class="os-row highlight"><span>Total</span><span>BDT <?= number_format($order['total'], 2) ?></span></div>
    </div>
  </div>

  <div class="action-btns">
    <a href="/orders.php" class="btn btn-primary btn-lg">
      <i class="fas fa-clock"></i> Track Order
    </a>
    <a href="/foods.php" class="btn btn-outline btn-lg">
      <i class="fas fa-utensils"></i> Order More
    </a>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>