# UniBites — University Food Ordering & Campus Delivery Platform
## Complete Frontend Project (HTML, CSS, JavaScript + XAMPP/PHP Ready)

---

## 📁 Project Structure

```
unibites/
│
├── index.html                  ← Main Home Page (after login)
├── css/
│   └── style.css               ← Global stylesheet (dark theme, components)
├── js/
│   └── cart.js                 ← Cart utility functions (localStorage)
└── pages/
    ├── signin.html             ← Login Page
    ├── signup.html             ← Register Page
    ├── foods.html              ← Full Food Menu with filter sidebar
    ├── canteen.html            ← Canteen Stalls (all 6 stalls, tab switching)
    ├── checkout.html           ← Order Checkout + Payment
    ├── orders.html             ← Pending Order Requests (Rider View)
    ├── history.html            ← My Order History
    ├── profile.html            ← User Profile + Recent Activity
    ├── earn.html               ← Earn as Partner / Rider page
    ├── earnings.html           ← Rider Earnings + Completed Deliveries
    └── support.html            ← Support / Contact page
```

---

## 🖥️ Pages Implemented

| Page | File | Matches Design |
|------|------|----------------|
| Sign In | `pages/signin.html` | ✅ Split panel, dark theme |
| Sign Up | `pages/signup.html` | ✅ Split panel, Google/Apple |
| Home Page | `index.html` | ✅ Hero, Popular Foods, Stalls, Reviews |
| Foods Menu | `pages/foods.html` | ✅ Hero, search, filter sidebar, grid |
| Canteen Stalls | `pages/canteen.html` | ✅ Tab nav, hero per stall, special foods |
| Order Checkout | `pages/checkout.html` | ✅ Billing form, cart summary, payment |
| Pending Orders | `pages/orders.html` | ✅ Rider delivery request cards |
| Order History | `pages/history.html` | ✅ Status badges, order cards |
| User Profile | `pages/profile.html` | ✅ Profile card, account info, activity |
| Earn as Partner | `pages/earn.html` | ✅ Hero, top riders, community stats |
| Rider Earnings | `pages/earnings.html` | ✅ Earnings summary, delivery list |
| Support | `pages/support.html` | ✅ Hero, contact cards, map, form |

---

## 🗄️ XAMPP / PHP Backend Integration

To connect to your XAMPP MySQL database, replace `localStorage` calls with PHP fetch requests.

### Database Tables (SQL)

```sql
-- Users Table
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(50),
  last_name VARCHAR(50),
  email VARCHAR(100) UNIQUE,
  password VARCHAR(255),
  phone VARCHAR(20),
  university_id VARCHAR(30),
  default_location TEXT,
  profile_photo VARCHAR(255),
  balance DECIMAL(10,2) DEFAULT 0,
  total_earnings DECIMAL(10,2) DEFAULT 0,
  role ENUM('user','admin') DEFAULT 'user',
  email_verified TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Canteen Stalls
CREATE TABLE stalls (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  slug VARCHAR(100),
  description TEXT,
  location VARCHAR(200),
  image VARCHAR(255),
  is_active TINYINT(1) DEFAULT 1
);

-- Food Items
CREATE TABLE foods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stall_id INT,
  name VARCHAR(100),
  description TEXT,
  price DECIMAL(10,2),
  category VARCHAR(50),
  image VARCHAR(255),
  is_available TINYINT(1) DEFAULT 1,
  is_popular TINYINT(1) DEFAULT 0,
  is_special TINYINT(1) DEFAULT 0,
  FOREIGN KEY (stall_id) REFERENCES stalls(id)
);

-- Orders
CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  full_name VARCHAR(100),
  phone VARCHAR(20),
  location TEXT,
  email VARCHAR(100),
  student_id VARCHAR(30),
  notes TEXT,
  subtotal DECIMAL(10,2),
  delivery_fee DECIMAL(10,2) DEFAULT 18,
  total DECIMAL(10,2),
  payment_method ENUM('cash','online') DEFAULT 'cash',
  payment_status ENUM('pending','paid','failed') DEFAULT 'pending',
  delivery_type ENUM('pickup','delivery') DEFAULT 'delivery',
  status ENUM('pending','confirmed','preparing','ready','accepted','picked_up','out_for_delivery','delivered','completed','cancelled') DEFAULT 'pending',
  rider_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Order Items
CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT,
  food_id INT,
  food_name VARCHAR(100),
  stall_name VARCHAR(100),
  quantity INT,
  price DECIMAL(10,2),
  FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- Deliveries
CREATE TABLE deliveries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT,
  rider_id INT,
  status ENUM('pending','accepted','picked_up','out_for_delivery','delivered','cancelled') DEFAULT 'pending',
  delivery_fee DECIMAL(10,2),
  accepted_at TIMESTAMP NULL,
  delivered_at TIMESTAMP NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (rider_id) REFERENCES users(id)
);

-- Ratings & Reviews
CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  food_id INT,
  order_id INT,
  rating INT CHECK (rating BETWEEN 1 AND 5),
  review TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### PHP API Example (api/login.php)
```php
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$conn = new mysqli('localhost', 'root', '', 'unibites');
$data = json_decode(file_get_contents('php://input'), true);
$email = $conn->real_escape_string($data['email']);
$password = $data['password'];
$result = $conn->query("SELECT * FROM users WHERE email='$email'");
$user = $result->fetch_assoc();
if ($user && password_verify($password, $user['password'])) {
  echo json_encode(['success' => true, 'user' => $user]);
} else {
  echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
}
```

---

## 🎨 Design System

| Token | Value |
|-------|-------|
| Primary (Orange) | `#FFA500` |
| Background | `#0D0D0D` |
| Card Background | `#1A1A1A` |
| Input Background | `#2A2A2A` |
| Border | `#333333` |
| Font | Inter (Google Fonts) |

---

## 🔗 Navigation Flow (SRS-aligned)

```
Sign In / Sign Up
      ↓
  Home Page
  ├── Foods → Food Details → Cart → Checkout → Payment → Orders
  ├── Canteen Stalls → Stall Page → Food Details → Cart
  ├── Earn as Partner → Pending Orders → Accept Delivery → Earnings
  ├── Profile → Edit Profile / Order History
  └── Support
```

---

## ✅ SRS Requirements Covered

- [x] User Registration & Login (Sign In / Sign Up)
- [x] Home Page (Popular Foods, Stalls, Reviews, How It Works)
- [x] Food Menu (Search, Filter by Price, Canteen, Category)
- [x] Canteen Stalls (6 stalls with tab navigation)
- [x] Stall-specific food pages with special food highlights
- [x] Add to Cart / Cart Management (localStorage)
- [x] Order Checkout (Billing, Delivery Fee, Payment Methods)
- [x] Cash on Delivery + Online Payment (SSLCommerz) options
- [x] Pending Order Requests (Rider acceptance flow)
- [x] Order History with Status Badges
- [x] User Profile (Edit, Change Password, Activity)
- [x] Earn as Rider (Top Riders, Community Stats, Earnings Banner)
- [x] Rider Earnings History (Completed deliveries + Earn per delivery)
- [x] Support Page (Contact Cards, Map, Get In Touch Form)
- [x] Consistent Footer across all pages
- [x] Responsive design (mobile-friendly)
- [x] Dark theme matching all uploaded designs