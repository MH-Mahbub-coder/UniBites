<?php
// ============================================================
//  UniBites — Shared Navbar (session-aware)
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$loggedIn  = isset($_SESSION['user_id']);
$userName  = $_SESSION['user_name']  ?? '';
$userRole  = $_SESSION['user_role']  ?? '';

// Detect active page
$currentPage = basename($_SERVER['PHP_SELF']);
function navActive($page, $current) {
    return ($current === $page) ? 'class="active"' : '';
}
?>
<nav class="navbar">
  <div class="logo" onclick="window.location.href='/index.php'" style="cursor:pointer;">
    <span>Uni</span><span>Bites</span>
  </div>

  <div class="nav-links">
    <a href="/unibites/index.php"          <?= navActive('index.php',       $currentPage) ?>>Home</a>
    <a href="/unibites/pages/canteen.php"  <?= navActive('canteen.php',     $currentPage) ?>>Canteen Stalls</a>
    <a href="/unibites/pages/foods.php"    <?= navActive('foods.php',       $currentPage) ?>>Foods</a>
    <a href="/unibites/pages/earn.php"     <?= navActive('earn.php',        $currentPage) ?>>Earn as Partner</a>
    <a href="/unibites/pages/support.php"  <?= navActive('support.php',     $currentPage) ?>>Support</a>
  </div>

  <div class="nav-right">
    <!-- Cart Icon -->
    <div class="nav-cart" onclick="window.location.href='/unibites/pages/checkout.php'">
      <i class="fas fa-shopping-cart"></i>
      <span class="cart-badge" id="cart-count">0</span>
    </div>

    <?php if ($loggedIn): ?>
      <!-- Profile Dropdown -->
      <div style="position:relative;" id="profile-dropdown-wrap">
        <img src="https://i.pravatar.cc/80?img=12" alt="User" class="nav-avatar"
             onclick="toggleDropdown()" style="cursor:pointer;">

        <div id="nav-dropdown" style="
          display:none; position:absolute; right:0; top:50px;
          background:var(--bg-card); border:1px solid var(--border);
          border-radius:var(--radius); min-width:200px; z-index:999;
          box-shadow:0 8px 30px rgba(0,0,0,0.4); overflow:hidden;">

          <!-- User Info -->
          <div style="padding:14px 16px; border-bottom:1px solid var(--border);">
            <div style="font-size:0.88rem;font-weight:700;"><?= htmlspecialchars($userName) ?></div>
            <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
          </div>

          <a href="/unibites/pages/profile.php" style="display:flex;align-items:center;gap:10px;padding:12px 16px;color:var(--text-gray);font-size:0.85rem;transition:var(--transition);text-decoration:none;"
             onmouseover="this.style.background='rgba(255,165,0,0.08)';this.style.color='var(--primary)'"
             onmouseout="this.style.background='none';this.style.color='var(--text-gray)'">
            <i class="fas fa-user" style="width:16px;"></i> My Profile
          </a>

          <a href="/unibites/pages/orders.php" style="display:flex;align-items:center;gap:10px;padding:12px 16px;color:var(--text-gray);font-size:0.85rem;transition:var(--transition);text-decoration:none;"
             onmouseover="this.style.background='rgba(255,165,0,0.08)';this.style.color='var(--primary)'"
             onmouseout="this.style.background='none';this.style.color='var(--text-gray)'">
            <i class="fas fa-clock" style="width:16px;"></i> Pending Orders
          </a>

          <a href="/unibites/pages/history.php" style="display:flex;align-items:center;gap:10px;padding:12px 16px;color:var(--text-gray);font-size:0.85rem;transition:var(--transition);text-decoration:none;"
             onmouseover="this.style.background='rgba(255,165,0,0.08)';this.style.color='var(--primary)'"
             onmouseout="this.style.background='none';this.style.color='var(--text-gray)'">
            <i class="fas fa-history" style="width:16px;"></i> Order History
          </a>

          <a href="/unibites/pages/earn.php" style="display:flex;align-items:center;gap:10px;padding:12px 16px;color:var(--text-gray);font-size:0.85rem;transition:var(--transition);text-decoration:none;"
             onmouseover="this.style.background='rgba(255,165,0,0.08)';this.style.color='var(--primary)'"
             onmouseout="this.style.background='none';this.style.color='var(--text-gray)'">
            <i class="fas fa-motorcycle" style="width:16px;"></i> Earn as Rider
          </a>

          <?php if ($userRole === 'admin'): ?>
          <a href="/unibites/admin/dashboard.php" style="display:flex;align-items:center;gap:10px;padding:12px 16px;color:var(--primary);font-size:0.85rem;font-weight:600;text-decoration:none;"
             onmouseover="this.style.background='rgba(255,165,0,0.08)'"
             onmouseout="this.style.background='none'">
            <i class="fas fa-cog" style="width:16px;"></i> Admin Panel
          </a>
          <?php endif; ?>

          <div style="border-top:1px solid var(--border);">
            <a href="/unibites/api/logout.php"
               style="display:flex;align-items:center;gap:10px;padding:12px 16px;color:#dc3545;font-size:0.85rem;font-weight:600;text-decoration:none;"
               onmouseover="this.style.background='rgba(220,53,69,0.08)'"
               onmouseout="this.style.background='none'">
              <i class="fas fa-sign-out-alt" style="width:16px;"></i> Logout
            </a>
          </div>
        </div>
      </div>

    <?php else: ?>
      <!-- Not logged in: show Login / Sign Up buttons -->
      <a href="/unibites/pages/signin.php" class="btn btn-outline btn-sm">Login</a>
      <a href="/unibites/pages/signup.php" class="btn btn-primary btn-sm">Sign Up</a>
    <?php endif; ?>
  </div>
</nav>

<script>
function toggleDropdown() {
  const dd = document.getElementById('nav-dropdown');
  dd.style.display = (dd.style.display === 'none' || !dd.style.display) ? 'block' : 'none';
}
// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
  const wrap = document.getElementById('profile-dropdown-wrap');
  if (wrap && !wrap.contains(e.target)) {
    const dd = document.getElementById('nav-dropdown');
    if (dd) dd.style.display = 'none';
  }
});
// Cart badge from localStorage
(function updateBadge(){
  const cart  = JSON.parse(localStorage.getItem('unibites_cart') || '[]');
  const count = cart.reduce((s,i) => s + i.qty, 0);
  document.querySelectorAll('#cart-count').forEach(el => el.textContent = count);
})();
</script>