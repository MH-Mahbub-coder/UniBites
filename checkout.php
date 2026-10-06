<?php
// ============================================================
//  UniBites — Order Checkout Page
//  - Buy Now items come via URL param ?buy_now=1
//  - Cart items come from JS localStorage
//  - On Place Order → saves to DB via API → routes to payment
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth.php';
requireLogin(); // must be logged in

$user = currentUser($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UniBites - Order Checkout</title>
  <link rel="stylesheet" href="/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .checkout-wrap { max-width: 1100px; margin: 0 auto; padding: 40px; }
    .checkout-title { font-size: 1.8rem; font-weight: 800; text-align: center; margin-bottom: 36px; color: var(--primary); }
    .checkout-grid { display: grid; grid-template-columns: 1fr 1.1fr; gap: 36px; }
    @media(max-width:900px){ .checkout-grid { grid-template-columns:1fr; } }

    .box { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 28px; }
    .box-title { font-size: 0.9rem; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 22px; }
    .box-title-dark { font-size: 0.9rem; font-weight: 700; color: var(--text-white); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; }

    .order-item-row { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border); }
    .oi-img { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; flex-shrink: 0; background: var(--bg-input); }
    .oi-name { font-size: 0.88rem; font-weight: 600; flex: 1; }
    .oi-stall { font-size: 0.72rem; color: var(--text-muted); }
    .oi-qty { display: flex; align-items: center; gap: 6px; }
    .qty-btn { width: 24px; height: 24px; border-radius: 4px; background: var(--bg-input); border: 1px solid var(--border); color: var(--text-white); cursor: pointer; font-size: 1rem; transition: var(--transition); }
    .qty-btn:hover { border-color: var(--primary); color: var(--primary); }
    .oi-price { font-size: 0.88rem; font-weight: 700; min-width: 70px; text-align: right; }

    .total-rows { margin-top: 16px; }
    .t-row { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid var(--border); font-size: 0.85rem; }
    .t-row span:first-child { color: var(--primary); font-weight: 600; }
    .t-row.final { font-weight: 800; font-size: 1rem; border: none; padding-top: 14px; }

    /* Payment Options */
    .pay-opt { display: flex; align-items: flex-start; gap: 14px; padding: 14px; border: 2px solid var(--border); border-radius: var(--radius-sm); margin-top: 10px; cursor: pointer; transition: var(--transition); }
    .pay-opt:hover, .pay-opt.selected { border-color: var(--primary); background: rgba(255,165,0,0.05); }
    .pay-opt input { accent-color: var(--primary); width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px; }
    .pay-label { font-size: 0.88rem; font-weight: 600; margin-bottom: 2px; }
    .pay-sub { font-size: 0.72rem; color: var(--text-muted); }
    .sslbadge { background: var(--primary); color: #000; font-size: 0.6rem; font-weight: 800; padding: 2px 6px; border-radius: 3px; display: inline-block; margin-top: 4px; }

    /* Online payment form */
    .online-form { display: none; background: var(--bg-card2); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 18px; margin-top: 12px; }
    .online-form.show { display: block; }

    .method-tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
    .method-tab { padding: 8px 16px; border-radius: var(--radius-sm); border: 2px solid var(--border); background: none; color: var(--text-gray); font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: var(--transition); font-family: inherit; }
    .method-tab.active, .method-tab:hover { border-color: var(--primary); color: var(--primary); background: rgba(255,165,0,0.08); }

    .card-fields, .mobile-fields { display: none; }
    .card-fields.show, .mobile-fields.show { display: block; }

    .card-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

    /* Terms */
    .terms-row { display: flex; align-items: flex-start; gap: 10px; margin-top: 16px; font-size: 0.8rem; color: var(--text-muted); }
    .terms-row input { accent-color: var(--primary); margin-top: 2px; }
    .terms-row a { color: var(--text-white); font-weight: 600; }

    .place-btn { width: 100%; padding: 15px; background: var(--primary); color: #000; font-size: 1rem; font-weight: 800; border: none; border-radius: var(--radius-sm); cursor: pointer; margin-top: 22px; letter-spacing: 1px; transition: var(--transition); font-family: inherit; }
    .place-btn:hover { background: var(--primary-dark); }
    .place-btn:disabled { background: var(--bg-input); color: var(--text-muted); cursor: not-allowed; }

    .empty-cart { text-align: center; padding: 60px; color: var(--text-muted); }
    .empty-cart i { font-size: 3rem; color: var(--primary); margin-bottom: 16px; display: block; }

    /* Delivery toggle */
    .delivery-toggle { display: flex; gap: 10px; margin-bottom: 18px; }
    .dtype-btn { flex: 1; padding: 10px; border: 2px solid var(--border); border-radius: var(--radius-sm); background: none; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: var(--transition); font-family: inherit; }
    .dtype-btn.active { border-color: var(--primary); color: var(--primary); background: rgba(255,165,0,0.08); }
  </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="checkout-wrap">
  <h1 class="checkout-title">Order Checkout</h1>

  <div id="empty-state" class="empty-cart" style="display:none;">
    <i class="fas fa-shopping-cart"></i>
    <h3>Your cart is empty</h3>
    <p>Add some food to continue</p>
    <a href="foods.php" class="btn btn-primary mt-16">Browse Foods</a>
  </div>

  <div class="checkout-grid" id="checkout-grid">
    <!-- LEFT: Billing -->
    <div class="box">
      <div class="box-title">Billing Details</div>

      <div class="form-group">
        <label class="form-label">Full Name*</label>
        <input type="text" class="form-control" id="full_name"
               value="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Phone*</label>
        <input type="tel" class="form-control" id="phone"
               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Your Location on Campus*</label>
        <input type="text" class="form-control" id="location"
               placeholder="e.g. Room 304, Block A, 3rd Floor"
               value="<?= htmlspecialchars($user['default_location'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" class="form-control" id="email"
               value="<?= htmlspecialchars($user['email']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Student ID</label>
        <input type="text" class="form-control" id="student_id"
               placeholder="UIU Student ID"
               value="<?= htmlspecialchars($user['university_id'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Order Notes (Optional)</label>
        <textarea class="form-control" id="notes" placeholder="Special notes for delivery..."></textarea>
      </div>

      <!-- Delivery Type Toggle -->
      <label class="form-label">Order Type</label>
      <div class="delivery-toggle">
        <button class="dtype-btn active" onclick="setDelivery('delivery',this)">
          <i class="fas fa-motorcycle"></i> Delivery
        </button>
        <button class="dtype-btn" onclick="setDelivery('pickup',this)">
          <i class="fas fa-store"></i> Pickup
        </button>
      </div>
    </div>

    <!-- RIGHT: Order Summary + Payment -->
    <div>
      <div class="box">
        <div class="box-title-dark">Your Order</div>

        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
          <span style="font-size:0.75rem;color:var(--primary);font-weight:700;text-transform:uppercase;">Product</span>
          <span style="font-size:0.75rem;color:var(--primary);font-weight:700;text-transform:uppercase;">Subtotal</span>
        </div>
        <hr style="border-color:var(--border);margin-bottom:0;">

        <div id="order-items"></div>

        <div class="total-rows">
          <div class="t-row"><span>Subtotal</span><span id="subtotal-val">BDT 0</span></div>
          <div class="t-row" id="delivery-fee-row"><span>Delivery Fee</span><span>BDT 18</span></div>
          <div class="t-row final"><span style="color:var(--primary);">Total</span><span id="total-val">BDT 0</span></div>
        </div>

        <hr style="border-color:var(--border);margin:18px 0;">

        <!-- Payment: Cash -->
        <div class="pay-opt selected" id="opt-cash" onclick="selectPayment('cash_on_delivery')">
          <input type="radio" name="payment" checked id="radio-cash">
          <div>
            <div class="pay-label"><i class="fas fa-money-bill-wave" style="color:var(--primary);margin-right:6px;"></i>Cash on Delivery</div>
            <div class="pay-sub">Pay when your food arrives</div>
          </div>
        </div>

        <!-- Payment: Online -->
        <div class="pay-opt" id="opt-online" onclick="selectPayment('online')">
          <input type="radio" name="payment" id="radio-online">
          <div>
            <div class="pay-label"><i class="fas fa-credit-card" style="color:var(--primary);margin-right:6px;"></i>Pay Online</div>
            <div class="pay-sub">Credit/Debit Card, bKash, Rocket, Nagad</div>
            <span class="sslbadge">SSLCOMMERZ</span>
          </div>
        </div>

        <!-- Online Payment Form (hidden until selected) -->
        <div class="online-form" id="online-form">
          <div class="method-tabs">
            <button class="method-tab active" onclick="setMethod('card',this)"><i class="fas fa-credit-card"></i> Card</button>
            <button class="method-tab" onclick="setMethod('bkash',this)">bKash</button>
            <button class="method-tab" onclick="setMethod('rocket',this)">Rocket</button>
            <button class="method-tab" onclick="setMethod('nagad',this)">Nagad</button>
          </div>

          <!-- Card Fields -->
          <div class="card-fields show" id="card-fields">
            <div class="form-group">
              <label class="form-label">Card Number</label>
              <input type="text" class="form-control" id="card_number" placeholder="1234 5678 9012 3456" maxlength="19" oninput="formatCard(this)">
            </div>
            <div class="form-group">
              <label class="form-label">Card Holder Name</label>
              <input type="text" class="form-control" id="card_holder" placeholder="Name on card">
            </div>
            <div class="card-row">
              <div class="form-group">
                <label class="form-label">Expiry (MM/YY)</label>
                <input type="text" class="form-control" id="card_expiry" placeholder="MM/YY" maxlength="5" oninput="formatExpiry(this)">
              </div>
              <div class="form-group">
                <label class="form-label">CVV</label>
                <input type="password" class="form-control" id="card_cvv" placeholder="•••" maxlength="4">
              </div>
            </div>
          </div>

          <!-- Mobile Banking Fields -->
          <div class="mobile-fields" id="mobile-fields">
            <div class="form-group">
              <label class="form-label" id="mobile-label">bKash Number</label>
              <input type="tel" class="form-control" id="mobile_number" placeholder="01XXXXXXXXX">
            </div>
            <div class="form-group">
              <label class="form-label">OTP / PIN</label>
              <input type="password" class="form-control" id="mobile_pin" placeholder="Enter OTP" maxlength="6">
            </div>
            <div style="background:rgba(255,165,0,0.08);border:1px solid rgba(255,165,0,0.2);border-radius:8px;padding:12px;font-size:0.78rem;color:var(--text-muted);">
              <i class="fas fa-info-circle" style="color:var(--primary);margin-right:6px;"></i>
              A verification request will be sent to your mobile number.
            </div>
          </div>
        </div>

        <!-- Terms -->
        <div class="terms-row">
          <input type="checkbox" id="terms">
          <label for="terms">I have read and agree to the <a href="#">Terms & Conditions, Privacy Policy and Refund Policy</a>*</label>
        </div>

        <button class="place-btn" id="place-btn" onclick="placeOrder()">
          <i class="fas fa-check-circle"></i> PLACE ORDER
        </button>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<div class="toast-container" id="toast-container"></div>

<script>
  // ============================================================
  //  Checkout Logic
  // ============================================================
  let cartItems   = [];
  let payMethod   = 'cash_on_delivery';
  let onlineMethod= 'card';
  let delivType   = 'delivery';
  const DELIVERY_FEE = 18;

  // ---- Load cart from localStorage (or buy_now param) ----
  function loadCart() {
    // Check if "Buy Now" mode (single item passed via sessionStorage)
    const buyNow = sessionStorage.getItem('unibites_buynow');
    if (buyNow) {
      cartItems = JSON.parse(buyNow);
      sessionStorage.removeItem('unibites_buynow');
    } else {
      cartItems = JSON.parse(localStorage.getItem('unibites_cart') || '[]');
    }

    if (!cartItems.length) {
      document.getElementById('empty-state').style.display   = 'block';
      document.getElementById('checkout-grid').style.display = 'none';
      return;
    }
    renderItems();
    updateTotals();
  }

  function renderItems() {
    const wrap = document.getElementById('order-items');
    wrap.innerHTML = '';
    cartItems.forEach((item, idx) => {
      wrap.innerHTML += `
        <div class="order-item-row">
          <div class="oi-img" style="background:var(--bg-input);display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-utensils" style="color:var(--primary);"></i>
          </div>
          <div style="flex:1;">
            <div class="oi-name">${item.name}</div>
            <div class="oi-stall">${item.stall}</div>
            <div class="oi-qty" style="margin-top:5px;">
              <button class="qty-btn" onclick="changeQty(${idx},-1)">-</button>
              <span style="min-width:20px;text-align:center;font-size:0.88rem;font-weight:600;">${item.qty}</span>
              <button class="qty-btn" onclick="changeQty(${idx},1)">+</button>
            </div>
          </div>
          <div class="oi-price">BDT ${(item.price * item.qty).toFixed(2)}</div>
        </div>`;
    });
  }

  function changeQty(idx, delta) {
    cartItems[idx].qty += delta;
    if (cartItems[idx].qty <= 0) cartItems.splice(idx, 1);
    if (!cartItems.length) {
      document.getElementById('empty-state').style.display   = 'block';
      document.getElementById('checkout-grid').style.display = 'none';
      return;
    }
    renderItems();
    updateTotals();
    localStorage.setItem('unibites_cart', JSON.stringify(cartItems));
    updateCartBadge();
  }

  function updateTotals() {
    const subtotal = cartItems.reduce((s, i) => s + i.price * i.qty, 0);
    const fee      = (delivType === 'delivery') ? DELIVERY_FEE : 0;
    const total    = subtotal + fee;
    document.getElementById('subtotal-val').textContent   = 'BDT ' + subtotal.toFixed(2);
    document.getElementById('total-val').textContent      = 'BDT ' + total.toFixed(2);
    document.getElementById('delivery-fee-row').querySelector('span:last-child').textContent =
      (delivType === 'delivery') ? 'BDT 18.00' : 'FREE (Pickup)';
  }

  function setDelivery(type, btn) {
    delivType = type;
    document.querySelectorAll('.dtype-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('delivery-fee-row').style.display = (type === 'pickup') ? 'none' : '';
    updateTotals();
  }

  function selectPayment(method) {
    payMethod = method;
    // Toggle UI
    document.getElementById('opt-cash').classList.toggle('selected',   method === 'cash_on_delivery');
    document.getElementById('opt-online').classList.toggle('selected', method === 'online');
    document.getElementById('radio-cash').checked   = (method === 'cash_on_delivery');
    document.getElementById('radio-online').checked = (method === 'online');
    document.getElementById('online-form').classList.toggle('show', method === 'online');
  }

  function setMethod(m, btn) {
    onlineMethod = m;
    document.querySelectorAll('.method-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const isCard   = (m === 'card');
    document.getElementById('card-fields').classList.toggle('show',   isCard);
    document.getElementById('mobile-fields').classList.toggle('show', !isCard);
    if (!isCard) {
      document.getElementById('mobile-label').textContent = m.charAt(0).toUpperCase() + m.slice(1) + ' Number';
    }
  }

  function formatCard(el) {
    el.value = el.value.replace(/\D/g,'').replace(/(.{4})/g,'$1 ').trim().substring(0,19);
  }

  function formatExpiry(el) {
    let v = el.value.replace(/\D/g,'');
    if (v.length >= 2) v = v.substring(0,2) + '/' + v.substring(2,4);
    el.value = v;
  }

  // ---- Validate online payment fields ----
  function validateOnlinePayment() {
    if (onlineMethod === 'card') {
      const num    = document.getElementById('card_number').value.replace(/\s/g,'');
      const holder = document.getElementById('card_holder').value.trim();
      const exp    = document.getElementById('card_expiry').value.trim();
      const cvv    = document.getElementById('card_cvv').value.trim();
      if (num.length < 16)   { showToast('Enter a valid 16-digit card number', 'error'); return false; }
      if (!holder)           { showToast('Enter card holder name', 'error'); return false; }
      if (exp.length < 5)    { showToast('Enter valid expiry date (MM/YY)', 'error'); return false; }
      if (cvv.length < 3)    { showToast('Enter valid CVV', 'error'); return false; }
    } else {
      const num = document.getElementById('mobile_number').value.trim();
      const pin = document.getElementById('mobile_pin').value.trim();
      if (num.length < 11) { showToast('Enter valid mobile number', 'error'); return false; }
      if (!pin)            { showToast('Enter OTP / PIN', 'error'); return false; }
    }
    return true;
  }

  // ---- MAIN: Place Order ----
  async function placeOrder() {
    const fullName  = document.getElementById('full_name').value.trim();
    const phone     = document.getElementById('phone').value.trim();
    const location  = document.getElementById('location').value.trim();
    const email     = document.getElementById('email').value.trim();
    const studentId = document.getElementById('student_id').value.trim();
    const notes     = document.getElementById('notes').value.trim();
    const terms     = document.getElementById('terms').checked;

    if (!fullName || !phone || !location) { showToast('Full name, phone and location are required', 'error'); return; }
    if (!terms)                           { showToast('Please agree to Terms & Conditions', 'error'); return; }
    if (!cartItems.length)                { showToast('Cart is empty!', 'error'); return; }

    if (payMethod === 'online' && !validateOnlinePayment()) return;

    const btn = document.getElementById('place-btn');
    btn.disabled   = true;
    btn.innerHTML  = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    // Build items payload
    const items = cartItems.map(i => ({
      food_id:    i.food_id || null,
      food_name:  i.name,
      stall_name: i.stall,
      quantity:   i.qty,
      unit_price: i.price
    }));

    try {
      // Step 1: Save order to DB
      const res  = await fetch('/unibites/api/place_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          full_name: fullName, phone, location, email,
          student_id: studentId, notes,
          payment_method: payMethod,
          delivery_type:  delivType,
          items
        })
      });
      const data = await res.json();

      if (!data.success) {
        showToast(data.message || 'Order failed', 'error');
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> PLACE ORDER';
        return;
      }

      // Clear cart from localStorage
      localStorage.removeItem('unibites_cart');
      updateCartBadge();

      // Step 2: If online payment → send payment details
      if (payMethod === 'online') {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Payment...';
        const payRes  = await fetch('/unibites/api/payment_process.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            order_id: data.order_id,
            method:   onlineMethod
          })
        });
        const payData = await payRes.json();

        if (!payData.success) {
          showToast(payData.message || 'Payment failed', 'error');
          btn.disabled  = false;
          btn.innerHTML = '<i class="fas fa-check-circle"></i> PLACE ORDER';
          return;
        }
        // Redirect to success page
        window.location.href = `/unibites/pages/order_success.php?order_id=${data.order_id}&txn=${payData.transaction_id}`;
      } else {
        // Cash on delivery → go to success
        window.location.href = `/unibites/pages/order_success.php?order_id=${data.order_id}`;
      }

    } catch (err) {
      showToast('Network error. Please try again.', 'error');
      btn.disabled  = false;
      btn.innerHTML = '<i class="fas fa-check-circle"></i> PLACE ORDER';
    }
  }

  // ---- Cart Badge ----
  function updateCartBadge() {
    const cart  = JSON.parse(localStorage.getItem('unibites_cart') || '[]');
    const count = cart.reduce((s, i) => s + i.qty, 0);
    document.querySelectorAll('#cart-count').forEach(el => el.textContent = count);
  }

  function showToast(msg, type = 'success') {
    let tc = document.getElementById('toast-container');
    const t  = document.createElement('div');
    t.className = `toast ${type}`;
    const icon  = type === 'success' ? 'check-circle' : 'exclamation-circle';
    const color = type === 'success' ? '#28a745' : '#dc3545';
    t.innerHTML = `<i class="fas fa-${icon}" style="color:${color};margin-right:8px;"></i>${msg}`;
    tc.appendChild(t);
    setTimeout(() => t.remove(), 4000);
  }

  // Init
  loadCart();
  updateCartBadge();
</script>
</body>
</html>