USE `unibites`;

-- Cached rating aggregates on foods (avoids AVG() on every menu page load)
ALTER TABLE `foods`
  ADD COLUMN `avg_rating`    DECIMAL(2,1) NOT NULL DEFAULT 0.0 AFTER `is_special`,
  ADD COLUMN `rating_count`  INT(11)      NOT NULL DEFAULT 0   AFTER `avg_rating`;

-- Cached rating aggregates on users (for riders)
ALTER TABLE `users`
  ADD COLUMN `avg_rating`    DECIMAL(2,1) NOT NULL DEFAULT 0.0 AFTER `total_earnings`,
  ADD COLUMN `rating_count`  INT(11)      NOT NULL DEFAULT 0   AFTER `avg_rating`;

-- Prevent rating the same food twice from the same order
ALTER TABLE `reviews`
  ADD CONSTRAINT `uq_reviews_order_food` UNIQUE (`order_id`, `food_id`);

-- Trigger: keep foods.avg_rating / rating_count in sync on new review
DELIMITER $$
CREATE TRIGGER `trg_reviews_after_insert`
AFTER INSERT ON `reviews`
FOR EACH ROW
BEGIN
  IF NEW.food_id IS NOT NULL THEN
    UPDATE `foods`
    SET rating_count = rating_count + 1,
        avg_rating = ((avg_rating * rating_count) + NEW.rating) / (rating_count + 1)
    WHERE id = NEW.food_id;
  END IF;
END$$
DELIMITER ;

-- Trigger: keep users.avg_rating / rating_count in sync on new rider rating
DELIMITER $$
CREATE TRIGGER `trg_rider_ratings_after_insert`
AFTER INSERT ON `rider_ratings`
FOR EACH ROW
BEGIN
  UPDATE `users`
  SET rating_count = rating_count + 1,
      avg_rating = ((avg_rating * rating_count) + NEW.rating) / (rating_count + 1)
  WHERE id = NEW.rider_id;
END$$
DELIMITER ;