USE `unibites`;
SET AUTOCOMMIT = 0;
START TRANSACTION;

-- Admin (password set by setup/seed_passwords.php after import)
INSERT INTO `users` (`id`,`first_name`,`last_name`,`email`,`password`,`role`,`is_active`,`email_verified`) VALUES
  (1,'Admin','UniBites','admin@uiu.ac.bd','PENDING_HASH','admin',1,1);

-- Shops
INSERT INTO `shops` (`id`,`name`,`slug`,`description`,`location`,`is_active`,`sort_order`,`created_by`) VALUES
  (1,"Khan's Kitchen",'khans-kitchen','Authentic campus flavors — burgers, rolls, wings and more.','Ground Floor, Main Cafeteria, UIU',1,1,1),
  (2,'Olympia','olympia','Hearty lunch platters, rice dishes and heavy meals.','Ground Floor, Main Cafeteria, UIU',1,2,1),
  (3,'Well Food','well-food','Fresh bakery, sweets, cakes and ice cream.','Ground Floor, East Wing, UIU',1,3,1),
  (4,'CP','cp','Crispy chicken snacks, balls, sausages and fried rice.','Ground Floor, Main Cafeteria, UIU',1,4,1),
  (5,'Brew Cafe','brew-cafe','Freshly brewed coffees, lattes and refreshing drinks.','Campus Brew Cafe, UIU',1,5,1),
  (6,'Cafe Neptune','cafe-neptune','Affordable everyday meals — parata, milk tea, jilapi.','Canteen Area, UIU',1,6,1);

-- Shop login accounts (one users row per shop, role='shop', linked via shop_id)
INSERT INTO `users` (`first_name`,`email`,`password`,`role`,`shop_id`,`is_active`,`email_verified`) VALUES
  ("Khan's Kitchen",'khans-kitchen@uiu.ac.bd','PENDING_HASH','shop',1,1,1),
  ('Olympia','olympia@uiu.ac.bd','PENDING_HASH','shop',2,1,1),
  ('Well Food','well-food@uiu.ac.bd','PENDING_HASH','shop',3,1,1),
  ('CP','cp@uiu.ac.bd','PENDING_HASH','shop',4,1,1),
  ('Brew Cafe','brew-cafe@uiu.ac.bd','PENDING_HASH','shop',5,1,1),
  ('Cafe Neptune','cafe-neptune@uiu.ac.bd','PENDING_HASH','shop',6,1,1);

-- Categories
INSERT INTO `categories` (`name`,`slug`,`icon`,`sort_order`) VALUES
  ('Burger','burger','fa-hamburger',1),('Pizza','pizza','fa-pizza-slice',2),
  ('Chicken','chicken','fa-drumstick-bite',3),('Coffee','coffee','fa-coffee',4),
  ('Drinks','drinks','fa-glass-cheers',5),('Snacks','snacks','fa-cookie',6),
  ('Dessert','dessert','fa-ice-cream',7),('Rice & Meal','rice-meal','fa-bowl-rice',8),
  ('Bread','bread','fa-bread-slice',9),('Tea','tea','fa-mug-hot',10);

-- Sample foods (Khan's Kitchen, shop_id=1)
INSERT INTO `foods` (`shop_id`,`category_id`,`name`,`price`,`image`,`is_available`,`is_popular`) VALUES
  (1,1,'BBQ Chicken Burger',50.00,'food_default.jpg',1,1),
  (1,3,'Chicken Roll',40.00,'food_default.jpg',1,1),
  (1,2,'Slice Pizza',70.00,'food_default.jpg',1,0);

-- Sample students (dept email format enforced)
INSERT INTO `users` (`first_name`,`last_name`,`email`,`password`,`phone`,`university_id`,`department`,`role`,`is_rider`,`is_active`,`email_verified`) VALUES
  ('Farhan','Rahman','farhan@cse.uiu.ac.bd','PENDING_HASH','018xxxxxxx','UIU-2022-045','CSE','student',0,1,1),
  ('Shakib','Hasan','shakib@cse.uiu.ac.bd','PENDING_HASH','013xxxxxxx','UIU-2021-078','CSE','student',1,1,1);

COMMIT;
