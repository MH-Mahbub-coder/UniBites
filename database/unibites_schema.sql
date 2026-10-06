-- ============================================================
--  UniBites — Full Database Schema (v2)
--  Adds: shops (replaces stalls), shop-linked user accounts,
--        order status tracking, uploaded food images
-- ============================================================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+06:00";

CREATE DATABASE IF NOT EXISTS `unibites`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `unibites`;

-- ============================================================
--  TABLE: users
--  role = 'student' | 'shop' | 'admin'
--  Students: email must be <name>@<dept>.uiu.ac.bd
--  Shop accounts: email = <shop-slug>@uiu.ac.bd, linked via shop_id
--  Admin: fixed admin@uiu.ac.bd
-- ============================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`               INT(11)       NOT NULL AUTO_INCREMENT,
  `first_name`       VARCHAR(50)   NOT NULL,
  `last_name`        VARCHAR(50)   DEFAULT NULL,
  `email`            VARCHAR(100)  NOT NULL,
  `password`         VARCHAR(255)  NOT NULL COMMENT 'bcrypt hashed',
  `phone`            VARCHAR(20)   DEFAULT NULL,
  `university_id`    VARCHAR(30)   DEFAULT NULL,
  `department`       VARCHAR(100)  DEFAULT NULL,
  `profile_photo`    VARCHAR(255)  DEFAULT 'default.png',
  `default_location` TEXT          DEFAULT NULL,
  `balance`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_earnings`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `role`             ENUM('student','shop','admin') NOT NULL DEFAULT 'student',
  `shop_id`          INT(11)       DEFAULT NULL COMMENT 'Set only when role = shop',
  `is_rider`         TINYINT(1)    NOT NULL DEFAULT 0,
  `email_verified`   TINYINT(1)    NOT NULL DEFAULT 0,
  `is_active`        TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role`     (`role`),
  KEY `idx_users_shop_id`  (`shop_id`),
  KEY `idx_users_is_rider` (`is_rider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: shops  (replaces old "stalls")
-- ============================================================
DROP TABLE IF EXISTS `shops`;
CREATE TABLE `shops` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(100) NOT NULL COMMENT 'used to build shop email: slug@uiu.ac.bd',
  `description` TEXT         DEFAULT NULL,
  `location`    VARCHAR(200) DEFAULT NULL,
  `cover_image` VARCHAR(255) DEFAULT 'shop_default.jpg',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`  INT(11)      NOT NULL DEFAULT 0,
  `created_by`  INT(11)      DEFAULT NULL COMMENT 'admin user id who created this shop',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shops_slug` (`slug`),
  KEY `idx_shops_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

-- ============================================================
--  TABLE: categories
-- ============================================================
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `slug`       VARCHAR(100) NOT NULL,
  `icon`       VARCHAR(50)  DEFAULT NULL,
  `sort_order` INT(11)      NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: foods
--  image = filename only (e.g. "abc123.jpg"), actual files live in
--  /unibites/uploads/foods/  — set by shop panel file upload
-- ============================================================
DROP TABLE IF EXISTS `foods`;
CREATE TABLE `foods` (
  `id`           INT(11)       NOT NULL AUTO_INCREMENT,
  `shop_id`      INT(11)       NOT NULL,
  `category_id`  INT(11)       DEFAULT NULL,
  `name`         VARCHAR(100)  NOT NULL,
  `description`  TEXT          DEFAULT NULL,
  `price`        DECIMAL(10,2) NOT NULL,
  `image`        VARCHAR(255)  DEFAULT 'food_default.jpg',
  `is_available` TINYINT(1)    NOT NULL DEFAULT 1,
  `is_popular`   TINYINT(1)    NOT NULL DEFAULT 0,
  `is_special`   TINYINT(1)    NOT NULL DEFAULT 0,
  `tag`          VARCHAR(50)   DEFAULT NULL,
  `sort_order`   INT(11)       NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_foods_shop_id`      (`shop_id`),
  KEY `idx_foods_category_id`  (`category_id`),
  KEY `idx_foods_is_available` (`is_available`),
  KEY `idx_foods_price`        (`price`),
  CONSTRAINT `fk_foods_shop_id`     FOREIGN KEY (`shop_id`)     REFERENCES `shops`      (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_foods_category_id` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: orders
-- ============================================================
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id`               INT(11)       NOT NULL AUTO_INCREMENT,
  `user_id`          INT(11)       NOT NULL,
  `shop_id`          INT(11)       NOT NULL COMMENT 'each order belongs to ONE shop (cart is per-shop at checkout)',
  `order_number`     VARCHAR(20)   NOT NULL,
  `full_name`        VARCHAR(100)  NOT NULL,
  `phone`            VARCHAR(20)   NOT NULL,
  `location`         TEXT          NOT NULL,
  `email`            VARCHAR(100)  DEFAULT NULL,
  `student_id`       VARCHAR(30)   DEFAULT NULL,
  `notes`            TEXT          DEFAULT NULL,
  `subtotal`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `delivery_fee`     DECIMAL(10,2) NOT NULL DEFAULT 18.00,
  `total`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method`   ENUM('cash_on_delivery','online') NOT NULL DEFAULT 'cash_on_delivery',
  `payment_status`   ENUM('pending','processing','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `transaction_id`   VARCHAR(100)  DEFAULT NULL,
  `delivery_type`    ENUM('pickup','delivery') NOT NULL DEFAULT 'delivery',
  `status`           ENUM(
                        'pending',
                        'confirmed',
                        'preparing',
                        'ready',
                        'rider_accepted',
                        'picked_up',
                        'out_for_delivery',
                        'delivered',
                        'completed',
                        'cancelled'
                      ) NOT NULL DEFAULT 'pending',
  `rider_id`         INT(11)       DEFAULT NULL,
  `cancelled_reason` TEXT          DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_order_number` (`order_number`),
  KEY `idx_orders_user_id`  (`user_id`),
  KEY `idx_orders_shop_id`  (`shop_id`),
  KEY `idx_orders_status`   (`status`),
  KEY `idx_orders_rider_id` (`rider_id`),
  KEY `idx_orders_created`  (`created_at`),
  CONSTRAINT `fk_orders_user_id`  FOREIGN KEY (`user_id`)  REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_shop_id`  FOREIGN KEY (`shop_id`)  REFERENCES `shops` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_rider_id` FOREIGN KEY (`rider_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: order_items
-- ============================================================
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id`          INT(11)       NOT NULL AUTO_INCREMENT,
  `order_id`    INT(11)       NOT NULL,
  `food_id`     INT(11)       DEFAULT NULL,
  `food_name`   VARCHAR(100)  NOT NULL,
  `quantity`    INT(11)       NOT NULL DEFAULT 1,
  `unit_price`  DECIMAL(10,2) NOT NULL,
  `total_price` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oi_order_id` (`order_id`),
  KEY `idx_oi_food_id`  (`food_id`),
  CONSTRAINT `fk_oi_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oi_food_id`  FOREIGN KEY (`food_id`)  REFERENCES `foods`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: order_status_log  (NEW — powers the order tracking page)
--  Every time an order's status changes, insert a row here.
-- ============================================================
DROP TABLE IF EXISTS `order_status_log`;
CREATE TABLE `order_status_log` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `order_id`   INT(11)      NOT NULL,
  `status`     VARCHAR(30)  NOT NULL,
  `note`       VARCHAR(255) DEFAULT NULL,
  `changed_by` INT(11)      DEFAULT NULL COMMENT 'user id who triggered the change (shop/rider/system)',
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_osl_order_id` (`order_id`),
  CONSTRAINT `fk_osl_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: deliveries
-- ============================================================
DROP TABLE IF EXISTS `deliveries`;
CREATE TABLE `deliveries` (
  `id`           INT(11)       NOT NULL AUTO_INCREMENT,
  `order_id`     INT(11)       NOT NULL,
  `rider_id`     INT(11)       DEFAULT NULL,
  `status`       ENUM(
                    'pending','accepted','picked_up',
                    'out_for_delivery','delivered','cancelled'
                  ) NOT NULL DEFAULT 'pending',
  `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 18.00,
  `rider_earn`   DECIMAL(10,2) NOT NULL DEFAULT 12.00,
  `accepted_at`  DATETIME      DEFAULT NULL,
  `picked_up_at` DATETIME      DEFAULT NULL,
  `delivered_at` DATETIME      DEFAULT NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_deliveries_order_id` (`order_id`),
  KEY `idx_deliveries_rider_id` (`rider_id`),
  KEY `idx_deliveries_status`   (`status`),
  CONSTRAINT `fk_deliveries_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_deliveries_rider_id` FOREIGN KEY (`rider_id`) REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: payments
-- ============================================================
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id`               INT(11)       NOT NULL AUTO_INCREMENT,
  `order_id`         INT(11)       NOT NULL,
  `user_id`          INT(11)       NOT NULL,
  `method`           ENUM('cash_on_delivery','card','bkash','rocket','nagad','online') NOT NULL,
  `amount`           DECIMAL(10,2) NOT NULL,
  `currency`         VARCHAR(5)    NOT NULL DEFAULT 'BDT',
  `status`           ENUM('pending','processing','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `transaction_id`   VARCHAR(100)  DEFAULT NULL,
  `paid_at`          DATETIME      DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payments_order_id` (`order_id`),
  KEY `idx_payments_user_id`  (`user_id`),
  CONSTRAINT `fk_payments_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_user_id`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: reviews
-- ============================================================
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id`         INT(11)    NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)    NOT NULL,
  `food_id`    INT(11)    DEFAULT NULL,
  `order_id`   INT(11)    DEFAULT NULL,
  `shop_id`    INT(11)    DEFAULT NULL,
  `rating`     TINYINT(1) NOT NULL,
  `review`     TEXT       DEFAULT NULL,
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_reviews_user_id`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_food_id`  FOREIGN KEY (`food_id`)  REFERENCES `foods`  (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reviews_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reviews_shop_id`  FOREIGN KEY (`shop_id`)  REFERENCES `shops`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: rider_ratings
-- ============================================================
DROP TABLE IF EXISTS `rider_ratings`;
CREATE TABLE `rider_ratings` (
  `id`          INT(11)    NOT NULL AUTO_INCREMENT,
  `delivery_id` INT(11)    NOT NULL,
  `rider_id`    INT(11)    NOT NULL,
  `customer_id` INT(11)    NOT NULL,
  `rating`      TINYINT(1) NOT NULL,
  `comment`     TEXT       DEFAULT NULL,
  `created_at`  TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rr_delivery_id` (`delivery_id`),
  CONSTRAINT `fk_rr_delivery_id` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rr_rider_id`    FOREIGN KEY (`rider_id`)    REFERENCES `users`      (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rr_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `users`      (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: notifications
-- ============================================================
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)      DEFAULT NULL,
  `title`      VARCHAR(150) NOT NULL,
  `message`    TEXT         NOT NULL,
  `type`       VARCHAR(50)  NOT NULL DEFAULT 'announcement',
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_notif_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: contact_messages
-- ============================================================
DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `phone`      VARCHAR(20)  DEFAULT NULL,
  `email`      VARCHAR(100) NOT NULL,
  `message`    TEXT         NOT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: admin_logs
-- ============================================================
DROP TABLE IF EXISTS `admin_logs`;
CREATE TABLE `admin_logs` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `admin_id`   INT(11)      NOT NULL,
  `action`     VARCHAR(200) NOT NULL,
  `table_name` VARCHAR(50)  DEFAULT NULL,
  `record_id`  INT(11)      DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_al_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
