USE `unibites`;

DROP TRIGGER IF EXISTS `trg_reviews_after_insert`;
DELIMITER $$
CREATE TRIGGER `trg_reviews_after_insert`
AFTER INSERT ON `reviews`
FOR EACH ROW
BEGIN
  IF NEW.food_id IS NOT NULL THEN
    UPDATE `foods`
    SET rating_count = (SELECT COUNT(*) FROM `reviews` WHERE food_id = NEW.food_id),
        avg_rating   = (SELECT ROUND(AVG(rating), 1) FROM `reviews` WHERE food_id = NEW.food_id)
    WHERE id = NEW.food_id;
  END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_rider_ratings_after_insert`;
DELIMITER $$
CREATE TRIGGER `trg_rider_ratings_after_insert`
AFTER INSERT ON `rider_ratings`
FOR EACH ROW
BEGIN
  UPDATE `users`
  SET rating_count = (SELECT COUNT(*) FROM `rider_ratings` WHERE rider_id = NEW.rider_id),
      avg_rating   = (SELECT ROUND(AVG(rating), 1) FROM `rider_ratings` WHERE rider_id = NEW.rider_id)
  WHERE id = NEW.rider_id;
END$$
DELIMITER ;