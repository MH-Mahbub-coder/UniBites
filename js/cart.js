// UniBites Cart Utilities
function getCart() {
  return JSON.parse(localStorage.getItem('unibites_cart') || '[]');
}

function escapeHtml(str) {
  return String(str ?? '').replace(/[&<>"']/g, c => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));
}

function jsAttr(str) {
  return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function saveCart(cart) {
  localStorage.setItem('unibites_cart', JSON.stringify(cart));
}

function getCartCount() {
  return getCart().reduce((sum, i) => sum + i.qty, 0);
}

function addItemToCart(name, price, stall) {
  const cart = getCart();
  const existing = cart.find(i => i.name === name && i.stall === stall);
  if (existing) existing.qty++;
  else cart.push({ name, price, stall, qty: 1 });
  saveCart(cart);
  updateAllCartBadges();
}

// "Buy Now" = a standalone single-item purchase, kept SEPARATE from the
// regular persistent cart. Stored under its own sessionStorage key that
// checkout.html checks first; the normal cart in localStorage is left
// untouched. Immediately redirects to checkout.
function buyNowItem(name, price, stall, checkoutUrl) {
  const buyNowCart = [{ name, price, stall, qty: 1 }];
  sessionStorage.setItem('unibites_buynow', JSON.stringify(buyNowCart));
  window.location.href = checkoutUrl;
}

function updateAllCartBadges() {
  const count = getCartCount();
  document.querySelectorAll('#cart-count').forEach(el => el.textContent = count);
}

function showToast(msg, type = 'success') {
  let tc = document.getElementById('toast-container');
  if (!tc) {
    tc = document.createElement('div');
    tc.className = 'toast-container';
    tc.id = 'toast-container';
    document.body.appendChild(tc);
  }
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
  const color = type === 'success' ? '#28a745' : '#dc3545';
  t.innerHTML = `<i class="fas fa-${icon}" style="color:${color};margin-right:8px;"></i>${msg}`;
  tc.appendChild(t);
  setTimeout(() => t.remove(), 3000);
}

// ============================================================
//  RATING SYSTEM UTILITIES
// ============================================================

function getRatingsStore() {
  return JSON.parse(localStorage.getItem('unibites_ratings') || '{"foods":{},"riders":{},"orders":{}}');
}

function saveRatingsStore(store) {
  localStorage.setItem('unibites_ratings', JSON.stringify(store));
}

function isOrderRated(orderId) {
  const store = getRatingsStore();
  return !!store.orders[orderId];
}

function submitOrderRating(orderId, riderName, riderRating, foodRatings, riderReview = '') {
  const store = getRatingsStore();
  if (store.orders[orderId]) return false;

  store.orders[orderId] = { riderName, riderRating, riderReview, foodRatings, ratedAt: new Date().toISOString() };

  if (riderName && riderRating > 0) {
    if (!store.riders[riderName]) store.riders[riderName] = { sum: 0, count: 0 };
    store.riders[riderName].sum += riderRating;
    store.riders[riderName].count += 1;
  }

  foodRatings.forEach(fr => {
    if (fr.rating > 0) {
      if (!store.foods[fr.key]) store.foods[fr.key] = { sum: 0, count: 0 };
      store.foods[fr.key].sum += fr.rating;
      store.foods[fr.key].count += 1;
    }
  });

  saveRatingsStore(store);
  return true;
}

function getFoodRatingDisplay(key, baseAvg, baseCount) {
  const store = getRatingsStore();
  const extra = store.foods[key] || { sum: 0, count: 0 };
  const totalCount = baseCount + extra.count;
  const totalSum = (baseAvg * baseCount) + extra.sum;
  const avg = totalCount > 0 ? (totalSum / totalCount) : 0;
  return { avg: Math.round(avg * 10) / 10, count: totalCount };
}

function getRiderRatingDisplay(riderName, baseAvg, baseCount) {
  const store = getRatingsStore();
  const extra = store.riders[riderName] || { sum: 0, count: 0 };
  const totalCount = baseCount + extra.count;
  const totalSum = (baseAvg * baseCount) + extra.sum;
  const avg = totalCount > 0 ? (totalSum / totalCount) : 0;
  return { avg: Math.round(avg * 10) / 10, count: totalCount };
}

function renderStarsHTML(avg) {
  const full = Math.floor(avg);
  const half = (avg - full) >= 0.5;
  const empty = 5 - full - (half ? 1 : 0);
  let html = '';
  for (let i = 0; i < full; i++) html += '<i class="fas fa-star"></i>';
  if (half) html += '<i class="fas fa-star-half-alt"></i>';
  for (let i = 0; i < empty; i++) html += '<i class="far fa-star"></i>';
  return html;
}

// Shared logout for any student-facing page with a navbar avatar.
// Shop and admin pages already have their own logout wired into their sidebars.
function studentLogout() {
  fetch('../logout.php', { method: 'POST' }).finally(() => {
    const depth = window.location.pathname.includes('/pages/') ? '' : 'pages/';
    window.location.href = depth + 'signin.html';
  });
}

function buildStarInput(containerId, initialValue = 0) {
  const el = document.getElementById(containerId);
  if (!el) return;
  el.dataset.rating = initialValue;

  function paint(val) {
    el.innerHTML = [1, 2, 3, 4, 5].map(v =>
      `<i class="${v <= val ? 'fas' : 'far'} fa-star" data-val="${v}"></i>`
    ).join('');
  }
  paint(initialValue);

  el.addEventListener('click', (e) => {
    const star = e.target.closest('i[data-val]');
    if (!star) return;
    const val = parseInt(star.dataset.val);
    el.dataset.rating = val;
    paint(val);
  });

  el.addEventListener('mouseover', (e) => {
    const star = e.target.closest('i[data-val]');
    if (!star) return;
    const hoverVal = parseInt(star.dataset.val);
    el.querySelectorAll('i').forEach(s => {
      const sv = parseInt(s.dataset.val);
      s.className = sv <= hoverVal ? 'fas fa-star' : 'far fa-star';
    });
  });
  el.addEventListener('mouseleave', () => paint(parseInt(el.dataset.rating)));
}

document.addEventListener('DOMContentLoaded', updateAllCartBadges);