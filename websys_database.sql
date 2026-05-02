-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.4.3 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for login
DROP DATABASE IF EXISTS `login`;
CREATE DATABASE IF NOT EXISTS `login` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `login`;

-- Dumping structure for table login.addons
DROP TABLE IF EXISTS `addons`;
CREATE TABLE IF NOT EXISTS `addons` (
  `addon_id` int NOT NULL AUTO_INCREMENT,
  `addon_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('Available','Unavailable') DEFAULT 'Available',
  PRIMARY KEY (`addon_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table login.addons: ~5 rows (approximately)
INSERT INTO `addons` (`addon_id`, `addon_name`, `price`, `status`) VALUES
	(1, 'Extra Shot', 15.00, 'Available'),
	(2, 'Oat Milk', 20.00, 'Available'),
	(3, 'Whipped Cream', 10.00, 'Available'),
	(4, 'Almond Milk', 25.00, 'Available'),
	(5, 'Brown Sugar Syrup', 15.00, 'Available');

-- Dumping structure for table login.audit_logs
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user` varchar(100) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `details` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table login.audit_logs: ~1 rows (approximately)
INSERT INTO `audit_logs` (`log_id`, `user`, `action`, `details`, `created_at`) VALUES
	(18, 'cent', 'Reset Logs', 'All audit logs cleared', '2026-04-29 16:18:33');

-- Dumping structure for table login.ingredients
DROP TABLE IF EXISTS `ingredients`;
CREATE TABLE IF NOT EXISTS `ingredients` (
  `ingredient_id` int NOT NULL AUTO_INCREMENT,
  `ingredient_name` varchar(255) DEFAULT NULL,
  `stock` int DEFAULT NULL,
  `low_stock_threshold` int DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'pcs',
  `image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ingredient_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table login.ingredients: ~21 rows (approximately)
INSERT INTO `ingredients` (`ingredient_id`, `ingredient_name`, `stock`, `low_stock_threshold`, `unit`, `image`) VALUES
	(3, 'Caramel Syrup', 20, 5, 'Bottle-based', '1776697458_Caramel_Syrup.webp'),
	(4, 'Salted Caramel Syrup', 15, 5, 'Bottle-based', '1776697509_Salted_Caramel_Syrup.webp'),
	(5, 'Caramel Sauce', 15, 5, 'Bottle-based', '1776697558_Caramel_Sauce.webp'),
	(6, 'Chocolate Syrup', 20, 5, 'Bottle-based', '1776697594_Chocolate_Syrup.webp'),
	(7, 'Chocolate Sauce', 20, 5, 'Bottle-based', '1776697622_Chocolate_Sauce.webp'),
	(8, 'White Chocolate Sauce', 20, 5, 'Bottle-based', '1776697658_White_Chocolate_Sauce (1).webp'),
	(9, 'Strawberry Syrup', 20, 5, 'Bottle-based', '1776701233_Strawberry_Syrup.webp'),
	(10, 'Strawberry Puree', 20, 5, 'Bottle-based', '1776701277_Strawberry_Puree.webp'),
	(11, 'Blueberry Syrup', 20, 5, 'Bottle-based', '1776701314_Blueberry_Syrup.webp'),
	(12, 'Blueberry Puree', 15, 5, 'Bottle-based', '1776701338_Blueberry_Puree.webp'),
	(13, 'Vanilla Syrup', 20, 5, 'Bottle-based', '1776701372_Vanilla_Syrup.webp'),
	(14, 'Milk', 20, 5, 'Box-based', '1776701431_Milk.webp'),
	(15, 'All Purpose Cream', 5, 5, 'Box-based', '1776701530_All_Purpose_Cream.webp'),
	(16, 'Cups', 30, 5, 'Box-based', '1776701567_Cup.webp'),
	(17, 'Straws', 100, 10, 'Pack-based', '1776701613_Straws.webp'),
	(18, 'Biscoff Biscuits', 20, 5, 'Pack-based', '1776701711_Biscoff_Biscuits.webp'),
	(19, 'Biscoff Sauce', 10, 3, 'Bottle-based', '1776701759_Biscoff_Sauce.webp'),
	(20, 'Crushed Oreo', 20, 5, 'Pack-based', '1776701824_Crushed_Oreo.webp'),
	(21, 'Matcha Powder', 20, 5, 'Pack-based', '1776701856_Matcha_Powder.webp'),
	(22, 'Condensed Milk', 15, 5, 'Can-based', '1776701935_Condensed_Milk.webp'),
	(23, ' Coffee Beans', 15, 5, 'Plastic-based', '1776702040_Coffee_Beans.webp');

-- Dumping structure for table login.products
DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `product_id` int NOT NULL AUTO_INCREMENT,
  `product_name` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table login.products: ~52 rows (approximately)
INSERT INTO `products` (`product_id`, `product_name`, `category`, `price`, `status`, `image`) VALUES
	(17, 'Americano', 'Hot Coffee', 59.00, 'Available', '1775783489_Americano.webp'),
	(19, 'Caramel Macchiato', 'Hot Coffee', 89.00, 'Unavailable', '1775784058_Caramel_Macchiato.webp'),
	(20, 'Cocoa Latte', 'Hot Coffee', 85.00, 'Available', '1775784240_Cocoa_Latte.webp'),
	(21, 'Latte', 'Hot Coffee', 75.00, 'Available', '1775784292_Latte.webp'),
	(22, 'Matcha Latte', 'Hot Coffee', 85.00, 'Available', '1775784311_Matcha_Latte.webp'),
	(23, 'Mocha', 'Hot Coffee', 85.00, 'Available', '1775784332_Mocha.webp'),
	(24, 'Salted Caramel', 'Hot Coffee', 85.00, 'Available', '1775784347_Salted_Caramel.webp'),
	(25, 'Spanish Latte', 'Hot Coffee', 85.00, 'Available', '1775784363_Spanish_Latte.webp'),
	(26, 'Vietnamese', 'Hot Coffee', 69.00, 'Available', '1775784378_Vietnamese.webp'),
	(27, 'White Mocha Latte', 'Hot Coffee', 85.00, 'Available', '1775784428_White_Mocha_Latte.webp'),
	(28, 'Strawberry Oreo', 'Non-Coffee', 89.00, 'Available', '1775784524_Strawberry_Oreo.webp'),
	(29, 'Strawberry Milk', 'Non-Coffee', 89.00, 'Available', '1775784548_Strawberry_Milk.webp'),
	(30, 'Strawberry Cocoa', 'Non-Coffee', 89.00, 'Unavailable', '1775784583_Strawberry_Cocoa.webp'),
	(31, 'Oreo Milk', 'Non-Coffee', 89.00, 'Available', '1775784606_Oreo_Milk.webp'),
	(32, 'Milo Dino', 'Non-Coffee', 89.00, 'Available', '1775784628_Milo_Dino.webp'),
	(33, 'Cocoa Latte', 'Non-Coffee', 89.00, 'Available', '1775784668_Cocoa_Latte (1).webp'),
	(34, 'Chocolate Milk', 'Non-Coffee', 89.00, 'Available', '1775784709_Chocolate_Milk.webp'),
	(35, 'Choco Oreo Milk', 'Non-Coffee', 89.00, 'Available', '1775784735_Choco_Oreo_Milk.webp'),
	(36, 'Caramel Milk', 'Non-Coffee', 89.00, 'Available', '1775784770_Caramel_Milk.webp'),
	(37, 'Blueberry Milk', 'Non-Coffee', 89.00, 'Available', '1775784791_Blueberry_Milk.webp'),
	(38, 'Biscoff Oreo', 'Non-Coffee', 89.00, 'Available', '1775784867_Biscoff_Oreo.webp'),
	(39, 'Biscoff Milk', 'Non-Coffee', 89.00, 'Available', '1775784886_Biscoff_Milk.webp'),
	(40, 'Vanilla Matcha', 'Matcha Series', 99.00, 'Unavailable', '1775785383_Vanilla_Matcha.webp'),
	(41, 'Strawberry Matcha', 'Matcha Series', 109.00, 'Available', '1775785403_Strawberry_Matcha.webp'),
	(42, 'Sea Salt Matcha', 'Matcha Series', 89.00, 'Available', '1775785427_Sea_Salt_Sub_Oat.webp'),
	(43, 'Matcha Oreo', 'Matcha Series', 89.00, 'Available', '1775785454_Matcha_Oreo.webp'),
	(44, 'Matcha Latte', 'Matcha Series', 89.00, 'Available', '1775785475_Matcha_Latte (1).webp'),
	(45, 'Blueberry Matcha', 'Matcha Series', 89.00, 'Available', '1775785508_Blueberry_Matcha.webp'),
	(46, 'White Mocha Latte', 'Iced Coffee', 89.00, 'Available', '1775785581_White_Mocha_Latte.webp'),
	(47, 'Vietnamese', 'Iced Coffee', 75.00, 'Available', '1775879191_Vietnamese.webp'),
	(48, 'Spanish Latte', 'Iced Coffee', 89.00, 'Available', '1775879213_Spanish_Latte.webp'),
	(49, 'Sea Salt Latte', 'Iced Coffee', 109.00, 'Available', '1775879228_Sea_Salt_Latte.webp'),
	(50, 'Salted Caramel', 'Iced Coffee', 89.00, 'Available', '1775879257_Salted_Caramel.webp'),
	(51, 'Oreo Latte', 'Iced Coffee', 95.00, 'Available', '1775879275_Oreo_Latte.webp'),
	(52, 'Mocha', 'Iced Coffee', 89.00, 'Available', '1775879299_Mocha.webp'),
	(53, 'Latte', 'Iced Coffee', 79.00, 'Available', '1775879329_Latte.webp'),
	(54, 'Matcha Latte', 'Iced Coffee', 1.00, 'Available', '1775879342_Matcha_Latte.webp'),
	(55, 'Cocoa Latte', 'Iced Coffee', 89.00, 'Available', '1775879370_Cocoa_Latte.webp'),
	(56, 'Caramel Mocha Latte', 'Iced Coffee', 99.00, 'Available', '1775879405_Caramel_Mocha_Latte.webp'),
	(57, 'Caramel Macchiato', 'Iced Coffee', 99.00, 'Available', '1775879440_Caramel_Macchiato.webp'),
	(58, 'Biscoff Latte', 'Iced Coffee', 119.00, 'Available', '1775879457_Biscoff_Latte.webp'),
	(59, 'Americano', 'Iced Coffee', 65.00, 'Available', '1775879479_Americano.webp'),
	(60, 'Dirty Matcha', 'Iced Coffee', 99.00, 'Available', '1775879536_Dirty_Matcha.webp'),
	(63, 'White Macha Sub Oat', 'Matcha Series', 109.00, 'Available', '1776271156_White_Matcha_Sub_Oat.webp'),
	(64, 'Plain Fries', 'Snacks', 50.00, 'Available', '1776271373_plain_fries.webp'),
	(65, 'Chicken Pops', 'Snacks', 85.00, 'Available', '1776271549_chicken_pops.webp'),
	(66, 'Chicken Pops w/ Fries', 'Snacks', 110.00, 'Available', '1776271590_chicken_pops_with_fries.webp'),
	(67, 'Cheese Fries', 'Snacks', 60.00, 'Available', '1776271640_cheese_fries.webp'),
	(68, 'Barbecue Fries', 'Snacks', 60.00, 'Available', '1776271669_barbecue_fries.webp'),
	(69, 'Extra Shot', 'Add Ons', 20.00, 'Available', '1776271711_extra_shot.webp'),
	(70, 'Sea Salt Cream', 'Add Ons', 20.00, 'Available', '1776271733_sea_salt_cream.webp'),
	(71, 'Cold Foam', 'Add Ons', 10.00, 'Available', '1776271758_cold_foam.webp');

-- Dumping structure for table login.sales
DROP TABLE IF EXISTS `sales`;
CREATE TABLE IF NOT EXISTS `sales` (
  `sales_id` int NOT NULL AUTO_INCREMENT,
  `product_id` int DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(50) DEFAULT 'Completed',
  `product_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`sales_id`),
  KEY `fk_sales_product` (`product_id`),
  CONSTRAINT `fk_sales_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table login.sales: ~9 rows (approximately)
INSERT INTO `sales` (`sales_id`, `product_id`, `product_name`, `quantity`, `total`, `created_at`, `status`, `product_image`) VALUES
	(25, 17, 'Americano', 1, 59.00, '2026-04-30 16:15:26', 'Cancelled', '1775783489_Americano.webp'),
	(26, 22, 'Matcha Latte', 2, 170.00, '2026-04-30 16:22:57', 'Completed', '1775784311_Matcha_Latte.webp'),
	(27, 60, 'Dirty Matcha', 1, 99.00, '2026-04-30 16:46:34', 'Completed', '1775879536_Dirty_Matcha.webp'),
	(28, 17, 'Americano', 1, 59.00, '2026-04-30 23:54:16', 'Completed', '1775783489_Americano.webp'),
	(29, 21, 'Latte', 1, 85.00, '2026-04-30 23:58:43', 'Completed', '1775784292_Latte.webp'),
	(30, 25, 'Spanish Latte', 1, 100.00, '2026-05-01 14:15:23', 'Completed', '1775784363_Spanish_Latte.webp'),
	(31, 39, 'Biscoff Milk', 3, 342.00, '2026-05-01 14:16:32', 'Completed', '1775784886_Biscoff_Milk.webp'),
	(32, 29, 'Strawberry Milk', 2, 198.00, '2026-05-01 14:23:49', 'Completed', '1775784548_Strawberry_Milk.webp'),
	(33, 42, 'Sea Salt Matcha', 1, 89.00, '2026-05-01 16:00:09', 'Completed', '1775785427_Sea_Salt_Sub_Oat.webp');

-- Dumping structure for table login.sales_void
DROP TABLE IF EXISTS `sales_void`;
CREATE TABLE IF NOT EXISTS `sales_void` (
  `void_id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int DEFAULT NULL,
  `reason` text,
  `voided_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`void_id`),
  KEY `fk_sales_void_sale` (`sale_id`),
  CONSTRAINT `fk_sales_void_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sales_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table login.sales_void: ~0 rows (approximately)

-- Dumping structure for table login.sale_addons
DROP TABLE IF EXISTS `sale_addons`;
CREATE TABLE IF NOT EXISTS `sale_addons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int NOT NULL,
  `addon_id` int DEFAULT NULL,
  `addon_name` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  CONSTRAINT `sale_addons_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sales_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table login.sale_addons: ~4 rows (approximately)
INSERT INTO `sale_addons` (`id`, `sale_id`, `addon_id`, `addon_name`, `price`) VALUES
	(1, 29, 3, 'Whipped Cream', 10.00),
	(2, 30, 1, 'Extra Shot', 15.00),
	(3, 31, 4, 'Almond Milk', 25.00),
	(4, 32, 3, 'Whipped Cream', 10.00);

-- Dumping structure for table login.settings
DROP TABLE IF EXISTS `settings`;
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_id` int NOT NULL AUTO_INCREMENT,
  `store_name` varchar(150) DEFAULT NULL,
  `business_hours` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`setting_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table login.settings: ~1 rows (approximately)
INSERT INTO `settings` (`setting_id`, `store_name`, `business_hours`, `currency`) VALUES
	(1, 'My Coffee Shop', '8AM - 10PM', 'PHP');

-- Dumping structure for table login.users
DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `role` varchar(20) DEFAULT NULL,
  `fullname` varchar(100) DEFAULT '',
  `status` varchar(20) DEFAULT 'active',
  `avatar` varchar(255) DEFAULT '',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table login.users: ~5 rows (approximately)
INSERT INTO `users` (`user_id`, `username`, `password`, `role`, `fullname`, `status`, `avatar`) VALUES
	(18, 'Alcoffee', '$2y$10$/SkVGKghlr2EgRczQY6Eh.wGmLksJp73iut8W1o9O7T686Im8doD2', 'staff', 'Alcoffee', 'active', '1776734079_ari.jpg'),
	(19, 'cent', '$2y$10$DAgkgzeBAHPjUARf7pjp1OE5p13/VMy5KTZoHcdtzZlsNcjp6ntWe', 'admin', 'cent', 'active', '1776733093_gojo.jpeg'),
	(23, 'piolo', '$2y$10$ycdPo3CPghUsPl1nJW6ISu2loH/Ww84hPjuNPjXjPdI.M9jCa.Psm', 'user', 'Piolo Pascual', 'active', ''),
	(24, 'alden', '$2y$10$VfgTg6fIppVk7YEfA3WAtOIhjk7PeXbK.RBR37VHyS69N965f7pya', 'cashier', 'Alden Richards', 'active', ''),
	(25, 'Jenruby', '$2y$10$3dUndxEwLJvAVnorJF8/suQsEQ0QEWQ/RSiXE1Q2kBTv0xIIOjJOi', 'user', 'Jennie Ruby', 'active', '');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
