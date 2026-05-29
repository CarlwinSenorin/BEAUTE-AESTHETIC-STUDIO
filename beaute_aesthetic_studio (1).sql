-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 26, 2026 at 05:03 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `beaute_aesthetic_studio`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `end_time` time NOT NULL,
  `services` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of service IDs' CHECK (json_valid(`services`)),
  `pax` int(11) DEFAULT 1,
  `client_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`client_details`)),
  `total_price` decimal(10,2) NOT NULL,
  `discount_applied` decimal(10,2) DEFAULT 0.00,
  `final_price` decimal(10,2) NOT NULL,
  `status` enum('pending','reserved','confirmed','completed','cancelled','no_show') DEFAULT 'pending',
  `payment_status` enum('pending','paid','refunded') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT 'pay_on_arrival',
  `notes` text DEFAULT NULL,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `follow_up_sent` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `deletion_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `user_id`, `staff_id`, `appointment_date`, `appointment_time`, `checked_in_at`, `end_time`, `services`, `pax`, `client_details`, `total_price`, `discount_applied`, `final_price`, `status`, `payment_status`, `payment_method`, `notes`, `reminder_sent`, `created_at`, `updated_at`, `follow_up_sent`, `deleted_at`, `deleted_by`, `deletion_reason`) VALUES
(136, 10, 10, '2026-04-29', '08:00:00', '2026-05-15 03:32:01', '09:00:00', '[40,38]', 1, '[{\"person_index\":1,\"service_id\":\"40\",\"service_name\":\"Facial\",\"staffId\":\"10\",\"staffName\":\"Klyde Raven Riva\",\"date\":\"2026-04-29\",\"time\":\"08:00:00\"},{\"person_index\":1,\"service_id\":\"38\",\"service_name\":\"Nails\",\"staffId\":\"11\",\"staffName\":\"Peter Paul Pogi San Diego\",\"date\":\"2026-04-29\",\"time\":\"08:30:00\"}]', 648.00, 0.00, 648.00, 'reserved', 'pending', 'cash', '', 0, '2026-04-29 06:55:09', '2026-05-26 14:51:48', 0, '2026-05-26 14:51:48', 10, 'Deleted by Admin'),
(137, 10, 10, '2026-05-25', '08:00:00', NULL, '08:30:00', '[\"38\"]', 1, '[{\"service_id\":38,\"service_name\":\"Nails\",\"staffId\":\"10\",\"staffName\":\"Klyde Raven\",\"date\":\"2026-05-25\",\"time\":\"08:00:00\"}]', 399.00, 0.00, 399.00, 'reserved', 'pending', 'pay_on_arrival', 'Booked via BeauteBot AI', 0, '2026-05-15 03:12:50', '2026-05-26 14:51:52', 0, '2026-05-26 14:51:52', 10, 'Deleted by Admin'),
(138, 10, 10, '2026-05-18', '11:00:00', '2026-05-15 03:33:56', '11:30:00', '[39]', 1, '[{\"person_index\":1,\"service_id\":\"39\",\"service_name\":\"Massage\",\"staffId\":\"10\",\"staffName\":\"Klyde Raven Riva\",\"date\":\"2026-05-18\",\"time\":\"11:00:00\"}]', 599.00, 0.00, 599.00, 'confirmed', 'pending', 'cash', '', 0, '2026-05-15 03:14:33', '2026-05-26 14:51:55', 0, '2026-05-26 14:51:55', 10, 'Deleted by Admin');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('new','read','replied') DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `phone`, `message`, `status`, `created_at`) VALUES
(1, 'test', 'itsmecarlwin27@gmail.com', '09954886744', 'test', 'new', '2026-04-12 04:16:54'),
(2, 'asd', 'itsmecarlwin27@gmail.com', 'asd', 'asd', 'new', '2026-04-28 01:18:06'),
(3, 'asd', 'itsmecarlwin27@gmail.com', '12345678', 'asd123', 'new', '2026-04-28 01:18:29');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'General',
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 5,
  `unit` varchar(50) NOT NULL DEFAULT 'pcs',
  `cost_per_unit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `linked_service_id` int(11) DEFAULT NULL,
  `status` enum('in_stock','low_stock','out_of_stock') DEFAULT 'in_stock',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `name`, `category`, `quantity`, `reorder_level`, `unit`, `cost_per_unit`, `linked_service_id`, `status`, `created_at`, `updated_at`) VALUES
(2, 'test', 'General', 5, 2, 'bottle', 299.00, NULL, 'in_stock', '2026-02-13 07:50:33', '2026-05-15 03:28:21'),
(3, 'Nail Polish', 'General', 3, 0, 'pcs', 499.98, 46, 'in_stock', '2026-05-15 03:27:45', '2026-05-15 03:28:04');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_log`
--

CREATE TABLE `inventory_log` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `change_type` enum('restock','deduct','adjust') NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `quantity_after` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_log`
--

INSERT INTO `inventory_log` (`id`, `inventory_id`, `change_type`, `quantity_change`, `quantity_after`, `notes`, `created_by`, `created_at`) VALUES
(1, 2, 'restock', 4, 4, 'Initial stock', 10, '2026-02-13 07:50:33'),
(2, 2, 'deduct', -1, 3, 'Manual deduction', 10, '2026-02-13 07:51:06'),
(3, 2, 'deduct', -1, 2, 'Manual deduction', NULL, '2026-02-19 11:23:02'),
(4, 2, 'restock', 5, 7, 'Manual restock', NULL, '2026-02-19 18:03:21'),
(5, 2, 'deduct', -8, 0, 'Manual deduction', NULL, '2026-03-08 18:22:44'),
(6, 2, 'deduct', -100, 0, 'Manual deduction', NULL, '2026-03-08 18:23:08'),
(7, 2, 'restock', 50, 50, 'Manual restock', NULL, '2026-03-08 18:23:21'),
(8, 2, 'deduct', -100, 0, 'Manual deduction', NULL, '2026-03-08 18:23:29'),
(9, 2, 'restock', 2, 2, 'Manual restock', NULL, '2026-03-08 18:25:17'),
(10, 2, 'restock', 1, 3, 'Manual restock', NULL, '2026-03-08 18:25:25'),
(11, 2, 'deduct', -1, 2, 'Manual deduction', NULL, '2026-03-08 18:25:31'),
(12, 3, 'restock', 4, 4, 'Initial stock', NULL, '2026-05-15 03:27:45'),
(13, 3, 'deduct', -1, 3, 'Manual deduction', NULL, '2026-05-15 03:28:04'),
(14, 2, 'restock', 3, 5, 'Manual restock', NULL, '2026-05-15 03:28:21');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `identifier` varchar(255) NOT NULL COMMENT 'Email or IP address',
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IPv4 or IPv6 address',
  `user_agent` varchar(500) DEFAULT NULL COMMENT 'Browser user agent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `identifier`, `attempt_time`, `success`, `ip_address`, `user_agent`) VALUES
(10, 'test2@gmail.com|::1', '2026-02-02 14:28:22', 1, NULL, NULL),
(12, 'test@gmail.com|::1', '2026-02-02 15:27:10', 1, NULL, NULL),
(18, 'carl@gmail.com|::1', '2026-02-07 06:53:59', 1, NULL, NULL),
(27, 'admin_admin@beauteaesthetic.com|::1', '2026-02-08 03:11:30', 1, NULL, NULL),
(30, 'senorincarlwin@gmail.com|::1', '2026-02-08 05:04:33', 0, NULL, NULL),
(33, 'admin_itsmecarlwin27@gmail.com|::1', '2026-02-12 14:15:45', 0, NULL, NULL),
(34, 'admin_itsmecarlwin27@gmail.com|::1', '2026-02-12 14:15:55', 0, NULL, NULL),
(37, 'itsmecarlwin27@gmail.com|192.168.1.50', '2026-02-12 15:19:31', 1, NULL, NULL),
(77, 'admin_test@gmail.com|::1', '2026-02-19 17:36:26', 0, NULL, NULL),
(81, 'itsmecarlwin27@gmail.com|192.168.1.51', '2026-02-19 17:45:02', 1, NULL, NULL),
(108, 'admin_john@gmail.om|::1', '2026-02-28 04:01:30', 1, NULL, NULL),
(117, 'admin_senorin@gmail.com|::1', '2026-03-03 04:41:05', 0, NULL, NULL),
(121, 'itsmecarlwin27@gmail.com|::1', '2026-03-06 16:34:50', 1, NULL, NULL),
(122, 'admin_john@gmail.com|::1', '2026-03-06 16:35:40', 1, NULL, NULL),
(123, 'admin_senorincarlwin@gmail.com|::1', '2026-03-06 16:37:36', 1, NULL, NULL),
(124, 'itsmecarlwin27@gmail.com|::1', '2026-03-06 16:38:10', 1, NULL, NULL),
(133, 'klyderavenriva@gmail.com|127.0.0.1', '2026-04-02 08:01:26', 1, NULL, NULL),
(142, 'admin_itsmecarlwin27@gmail.com|127.0.0.1', '2026-04-12 04:04:11', 0, NULL, NULL),
(170, 'test_user_april2026_1@example.com|127.0.0.1', '2026-04-17 20:50:45', 1, NULL, NULL),
(173, 'itsmecarlwin27@gmail.com|192.168.254.138', '2026-04-18 13:27:52', 1, NULL, NULL),
(261, 'client@test.com|127.0.0.1', '2026-04-27 11:41:01', 0, NULL, NULL),
(262, 'admin@beauteaesthetic.com|127.0.0.1', '2026-04-27 11:41:12', 0, NULL, NULL),
(263, 'admin@beauteaesthetic.com|127.0.0.1', '2026-04-27 11:41:25', 0, NULL, NULL),
(271, 'admin_john@gmail.com|127.0.0.1', '2026-04-27 12:14:32', 1, NULL, NULL),
(272, 'admin_peter@gmail.com|127.0.0.1', '2026-04-27 12:15:12', 1, NULL, NULL),
(281, 'carlwin@gmail.com|127.0.0.1', '2026-04-27 14:32:12', 0, NULL, NULL),
(282, 'test12345@test.com|127.0.0.1', '2026-04-27 14:34:08', 1, NULL, NULL),
(288, 'admin_patrick@example.com|127.0.0.1', '2026-04-27 15:40:32', 1, NULL, NULL),
(304, 'itsmecarlwin27@gmail.com|192.168.1.5', '2026-04-27 18:56:18', 1, NULL, NULL),
(305, 'itsmecarlwin27@gmail.com|192.168.1.5', '2026-04-27 19:01:54', 1, NULL, NULL),
(314, 'admin_admin@beauteaesthetic.com|127.0.0.1', '2026-04-28 02:44:17', 0, NULL, NULL),
(315, 'admin_admin@beauteaesthetic.com|127.0.0.1', '2026-04-28 02:44:21', 0, NULL, NULL),
(317, 'patrick@gmail.com|127.0.0.1', '2026-04-28 03:04:43', 1, NULL, NULL),
(318, 'testuser@example.com|127.0.0.1', '2026-04-28 03:08:28', 1, NULL, NULL),
(329, 'admin_carlwin@example.com|127.0.0.1', '2026-04-29 07:09:29', 1, NULL, NULL),
(335, 'senorincarlwin@gmail.com|127.0.0.1', '2026-05-15 03:10:14', 0, NULL, NULL),
(340, 'admin_klyderavenriva@gmail.com|127.0.0.1', '2026-05-15 03:36:01', 1, NULL, NULL),
(341, 'admin_klyderavenriva@gmail.com|127.0.0.1', '2026-05-15 03:37:41', 1, NULL, NULL),
(342, 'itsmecarlwin27@gmail.com|127.0.0.1', '2026-05-26 14:50:56', 1, NULL, NULL),
(343, 'admin_senorincarlwin@gmail.com|127.0.0.1', '2026-05-26 14:51:16', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `services` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of service IDs' CHECK (json_valid(`services`)),
  `pax` int(11) NOT NULL DEFAULT 1,
  `original_price` decimal(10,2) NOT NULL,
  `discounted_price` decimal(10,2) NOT NULL,
  `discount_percentage` decimal(5,2) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`id`, `name`, `description`, `services`, `pax`, `original_price`, `discounted_price`, `discount_percentage`, `image_url`, `valid_from`, `valid_until`, `status`, `created_at`, `updated_at`) VALUES
(11, 'Graduation Package', 'Test', '[\"46\",\"38\",\"47\",\"39\"]', 1, 1847.00, 1569.95, 15.00, NULL, '2026-05-15', '2026-05-22', 'active', '2026-05-15 03:23:29', '2026-05-15 03:23:29');

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_purchase` decimal(10,2) DEFAULT NULL,
  `applicable_services` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of service IDs, NULL for all' CHECK (json_valid(`applicable_services`)),
  `valid_from` date NOT NULL,
  `valid_until` date NOT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(100) DEFAULT NULL,
  `capacity` int(11) DEFAULT 1,
  `is_accessible` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `name`, `type`, `capacity`, `is_accessible`, `status`, `created_at`) VALUES
(1, 'Room A', 'Manicure/Pedicure', 3, 1, 'active', '2026-04-25 19:26:41'),
(2, 'Room B', 'Skin/Facial', 1, 1, 'active', '2026-04-25 19:26:41'),
(3, 'Room C', 'Lashes/Threading', 2, 0, 'active', '2026-04-25 19:26:41'),
(4, 'Spa Room 1', 'Massage', 1, 1, 'active', '2026-04-25 19:26:41'),
(5, 'Spa Room 2', 'Massage', 1, 1, 'active', '2026-04-25 19:26:41');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` enum('nails','eyebrows','lashes','wax','massages','facial','skin_slimming') NOT NULL,
  `description` text DEFAULT NULL,
  `duration` int(11) NOT NULL COMMENT 'Duration in minutes',
  `base_price` decimal(10,2) NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `category`, `description`, `duration`, `base_price`, `image_url`, `status`, `created_at`, `updated_at`) VALUES
(37, 'Lashes', 'lashes', 'Test', 30, 1500.00, 'assets/images/services/service_6996ec404af45_bf0c611599be372e.jpg', 'active', '2026-02-19 10:56:00', '2026-02-19 10:56:00'),
(38, 'Nails', 'nails', 'Nails', 30, 399.00, 'assets/images/services/service_6996ed2a2457b_c466bca69397c686.jpg', 'active', '2026-02-19 10:56:29', '2026-02-19 10:59:54'),
(39, 'Massage', 'massages', 'Massage', 30, 599.00, 'assets/images/services/service_6996ec7a63e9f_4b7894b65c039b0a.jpg', 'active', '2026-02-19 10:56:58', '2026-02-19 10:56:58'),
(40, 'Facial', 'facial', '', 15, 249.00, 'assets/images/services/service_6996edb29fdbd_fb67e36635d880e1.webp', 'active', '2026-02-19 11:02:10', '2026-02-19 11:02:10'),
(45, 'Basic Manicure', 'nails', '', 30, 250.00, 'assets/images/services/service_69da9b3d34465_e7d54872373ee273.jpg', 'active', '2026-04-11 19:04:29', '2026-04-11 19:04:29'),
(46, 'Acrylic Nails', 'nails', '', 30, 350.00, 'assets/images/services/service_69da9bb907021_9759bd1d572842f1.jpg', 'active', '2026-04-11 19:06:33', '2026-04-11 19:06:33'),
(47, 'Eyelash Extension', 'lashes', '', 30, 499.00, 'assets/images/services/service_69da9c30cc241_c5198205d67a813e.jpg', 'active', '2026-04-11 19:08:32', '2026-04-11 19:08:32'),
(48, 'Swedish Massage', 'massages', '', 30, 699.00, 'assets/images/services/service_69da9cd9a1e55_0d6a8c9a8bc9de5b.webp', 'active', '2026-04-11 19:11:21', '2026-04-11 19:11:21'),
(49, 'Basic Facial', 'facial', '', 15, 499.00, 'assets/images/services/service_69da9d91dc7ff_474caba907210453.jpg', 'active', '2026-04-11 19:14:25', '2026-04-11 19:14:25'),
(50, 'Manicure', 'nails', 'Test', 15, 199.99, 'assets/images/services/service_6a069127c83ba_2f1c85e990f73a39.jpg', 'active', '2026-05-15 03:21:11', '2026-05-15 03:21:11');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'business_hours_start', '09:00', '2026-03-06 15:53:09'),
(2, 'business_hours_end', '18:00', '2026-03-06 15:53:09'),
(3, 'appointment_interval', '15', '2026-01-26 18:33:52'),
(4, 'booking_advance_days', '60', '2026-01-26 18:33:52'),
(5, 'cancellation_hours', '24', '2026-01-26 18:33:52'),
(6, 'sms_enabled', 'true', '2026-04-25 16:27:42'),
(7, 'email_enabled', 'true', '2026-01-26 18:33:52'),
(8, 'reminder_hours_before', '24', '2026-01-26 18:33:52'),
(9, 'peak_hour_start', '11:00', '2026-02-13 03:33:45'),
(10, 'peak_hour_end', '14:00', '2026-02-13 03:33:45'),
(11, 'peak_hour_surcharge', '10', '2026-02-13 03:33:45'),
(13, 'sms_sender_name', 'BeauteStudio', '2026-02-13 03:33:45'),
(14, 'follow_up_hours_after', '2', '2026-02-13 03:33:45'),
(18, 'sms_api_key', 'uk_Vh88C-Kw-8UETkRmu4Q2NU8ccDdgaLLou8L20Vy9dCWETkwI0gJnJJ1QP9phWaRQ', '2026-04-25 17:16:42'),
(19, 'smtp_host', 'smtp.gmail.com', '2026-02-20 14:58:25'),
(20, 'smtp_port', '587', '2026-02-20 14:58:25'),
(21, 'smtp_secure', 'tls', '2026-02-20 14:58:25'),
(22, 'smtp_user', 'senorincarlwin@gmail.com', '2026-02-20 15:07:41'),
(23, 'smtp_pass', 'syggfoqvwrchjvue', '2026-02-20 15:37:05'),
(93, 'sms_from_number', '639093373270', '2026-04-25 16:45:26');

-- --------------------------------------------------------

--
-- Table structure for table `skills`
--

CREATE TABLE `skills` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `skills`
--

INSERT INTO `skills` (`id`, `name`, `description`) VALUES
(1, 'Manicure', NULL),
(2, 'Pedicure', NULL),
(3, 'Gel Polish', NULL),
(4, 'Nail Art', NULL),
(5, 'Acrylic', NULL),
(6, 'Threading', NULL),
(7, 'Waxing', NULL),
(8, 'Tinting', NULL),
(9, 'Microblading', NULL),
(10, 'Lash Extensions', NULL),
(11, 'Lash Lift', NULL),
(12, 'Facial', NULL),
(13, 'Massage', NULL),
(14, 'Skin Rejuvenation', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `specialization` text DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `availability` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`availability`)),
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `efficiency_score` decimal(3,2) DEFAULT 1.00,
  `current_load` int(11) DEFAULT 0,
  `max_daily_capacity` int(11) DEFAULT 300
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `user_id`, `specialization`, `bio`, `availability`, `rating`, `total_reviews`, `efficiency_score`, `current_load`, `max_daily_capacity`) VALUES
(5, 14, 'Facial', '', '{\"monday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"tuesday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"wednesday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"thursday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"friday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"saturday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"sunday\":{\"active\":false,\"start\":\"\",\"end\":\"\"}}', 4.80, 0, 0.95, 20, 300),
(10, 27, 'Nails, Eyebrows, Lashes, Wax, Massages, Facial, Skin &amp; Slimming', '', '{\"monday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"tuesday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"wednesday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"thursday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"friday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"saturday\":{\"active\":true,\"start\":\"09:00\",\"end\":\"18:00\"},\"sunday\":{\"active\":false,\"start\":\"\",\"end\":\"\"}}', 4.50, 0, 0.85, 50, 300),
(11, 28, 'Nails, Lashes, Massages, Facial', '', '{\"monday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"tuesday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"wednesday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"thursday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"friday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"saturday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"sunday\":{\"active\":false,\"start\":\"09:00\",\"end\":\"18:00\"}}', 0.00, 0, 1.00, 0, 300),
(12, 33, 'Massages', '', '{\"monday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"tuesday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"wednesday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"thursday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"friday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"saturday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"sunday\":{\"active\":false,\"start\":\"09:00\",\"end\":\"18:00\"}}', 0.00, 0, 1.00, 0, 300),
(13, 34, 'Nails, Lashes', '', '{\"monday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"tuesday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"wednesday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"thursday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"friday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"saturday\":{\"active\":true,\"start\":\"08:00\",\"end\":\"17:00\"},\"sunday\":{\"active\":false,\"start\":\"09:00\",\"end\":\"18:00\"}}', 0.00, 0, 1.00, 0, 300);

-- --------------------------------------------------------

--
-- Table structure for table `staff_skills`
--

CREATE TABLE `staff_skills` (
  `staff_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `proficiency_level` int(11) DEFAULT 1 COMMENT '1-5 scale'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_skills`
--

INSERT INTO `staff_skills` (`staff_id`, `skill_id`, `proficiency_level`) VALUES
(5, 1, 5),
(5, 7, 4),
(10, 10, 5),
(10, 12, 5);

-- --------------------------------------------------------

--
-- Table structure for table `temporary_selections`
--

CREATE TABLE `temporary_selections` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `duration` int(11) DEFAULT 60
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `temporary_selections`
--

INSERT INTO `temporary_selections` (`id`, `staff_id`, `appointment_date`, `appointment_time`, `session_id`, `identifier`, `expires_at`, `duration`) VALUES
(393, 10, '2026-05-18', '11:00:00', 'g968mrn9uamumcanrfcvn25sto', '1778814834530l02awrs7x', '2026-05-15 03:29:15', 30);

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `staff_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `testimonials`
--

INSERT INTO `testimonials` (`id`, `user_id`, `appointment_id`, `rating`, `review_text`, `status`, `created_at`, `staff_id`) VALUES
(3, 10, NULL, 5, 'Good', 'approved', '2026-02-19 11:23:59', NULL),
(4, 10, NULL, 5, 'good', 'approved', '2026-03-06 16:36:07', 5),
(5, 10, NULL, 5, 'ok', 'approved', '2026-03-06 16:36:22', 5),
(6, 10, NULL, 5, 'test', 'approved', '2026-04-25 17:28:39', 10);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('client','admin','staff') DEFAULT 'client',
  `status` enum('active','inactive') DEFAULT 'active',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `profile_picture` varchar(500) DEFAULT NULL,
  `tier` enum('standard','premium','vip') DEFAULT 'standard'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `phone`, `password`, `role`, `status`, `reset_token`, `reset_expires`, `created_at`, `updated_at`, `last_login`, `last_ip`, `profile_picture`, `tier`) VALUES
(2, 'Admin', 'User', 'senorincarlwin@gmail.com', '1234567890', '$2y$10$b/vu4lyhaTB/4Iz9n3yuzew3NmtkNWjLeBBwb9RuRkS3Oz2s9jJaq', 'admin', 'active', NULL, NULL, '2026-01-26 19:38:06', '2026-05-26 14:51:16', '2026-05-26 14:51:16', '127.0.0.1', NULL, 'standard'),
(10, 'Carlwin', 'Senorin', 'itsmecarlwin27@gmail.com', '09954886744', '$2y$10$zDnaL9yTBWde2fZDoaNoAeZzTgdoipSsN6fbzlWFwWpseaY1chP.6', 'client', 'active', '2d6ec13e3543af6e6151c11a31a414493236f786862a293e1e0f14babba1b3b5', '2026-04-12 04:36:50', '2026-02-07 07:02:11', '2026-05-26 14:50:56', '2026-05-26 14:50:56', '127.0.0.1', NULL, 'standard'),
(14, 'John', 'Doe', 'john@gmail.com', '09123456789', '$2y$10$exXZc2tZ40PM5mzDS38LBeq5GQ1IDfwqrIqsdORIfNupmOK5QZsa6', 'staff', 'active', '164fc584fd60ea4039794046c3cc9ad3cf9abe44c9c78a6d8284543f14a3731d', '2026-04-21 00:42:42', '2026-03-01 16:07:30', '2026-04-27 12:14:32', '2026-04-27 12:14:32', '127.0.0.1', NULL, 'standard'),
(27, 'Klyde Raven', 'Riva', 'klyderavenriva@gmail.com', '09123456789', '$2y$10$P28.sJUAFpaCn1UOov5Jq.1vJzdfVMlT.vhy/RjlPxGHAZCyK8vfe', 'staff', 'active', NULL, NULL, '2026-04-20 15:59:16', '2026-05-15 03:37:42', '2026-05-15 03:37:42', '127.0.0.1', NULL, 'standard'),
(28, 'Peter Paul Pogi', 'San Diego', 'peter@gmail.com', '09123456789', '$2y$10$D5Cqe5IAEMQAdGfKBhMRbuNranr63APrSAPqGoW.1P5hYN4WwwvOO', 'staff', 'active', NULL, NULL, '2026-04-20 16:00:36', '2026-04-27 17:45:06', '2026-04-27 12:15:12', '127.0.0.1', NULL, 'standard'),
(29, 'Patrick', 'Pascual', 'patrick@gmail.com', '09664074261', '$2y$10$btyv2g6b3qE5sfXnLQKkYe4/qWVCbtpix02H1JYy/oZGB1sNInIp6', 'client', 'active', NULL, NULL, '2026-04-27 08:10:01', '2026-04-28 03:04:43', '2026-04-28 03:04:43', '127.0.0.1', NULL, 'standard'),
(30, 'Test', 'User', 'testuser@example.com', '09171234567', '$2y$10$MrCBIfu993NSi4tSZpqt/.24G48jMrzHQ6NQviGXYFR7HSpnKsTha', 'client', 'active', NULL, NULL, '2026-04-27 11:42:52', '2026-04-28 03:08:28', '2026-04-28 03:08:28', '127.0.0.1', NULL, 'standard'),
(33, 'carlwin', 'Senorin', 'carlwin@example.com', '09234456778', '$2y$10$WmWjTEbgeIA8oNx.7Of52ut3hgzdFoHMeJkTawXiXyPP9EGnHoF0C', 'staff', 'active', NULL, NULL, '2026-04-27 12:08:11', '2026-04-29 07:09:29', '2026-04-29 07:09:29', '127.0.0.1', NULL, 'standard'),
(34, 'Patrick', 'Pascual', 'patrick@example.com', '09123545674', '$2y$10$7AjuvFQNew6hRcdkzCO17uwJMo3fBLDs00/zJeblyggSI.3/e8SmW', 'staff', 'active', NULL, NULL, '2026-04-27 12:08:51', '2026-04-27 15:40:32', '2026-04-27 15:40:32', '127.0.0.1', NULL, 'standard'),
(35, 'Test', 'User', 'test12345@test.com', '09123456789', '$2y$10$bp3wenvsKnmDKg8zQYp66eQkSFcl4bv5rIBAFrnsmX2yhW4gWpQf6', 'client', 'active', NULL, NULL, '2026-04-27 14:33:51', '2026-04-27 14:34:08', '2026-04-27 14:34:08', '127.0.0.1', NULL, 'standard'),
(36, 'Carlwin', 'Senorin', 'Carlwinsenorin@gmail.com', '09664074261', '$2y$10$jsnLyHIvJTIzstmH66QvreVC3A3Z7tKDd1.r/0LAn.riW4imXZbSK', 'client', 'active', NULL, NULL, '2026-05-15 03:07:01', '2026-05-15 03:07:01', NULL, NULL, NULL, 'standard');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `idx_appointment_date` (`appointment_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_deleted_at` (`deleted_at`),
  ADD KEY `deleted_by` (`deleted_by`),
  ADD KEY `idx_active_appointments` (`deleted_at`,`appointment_date`,`staff_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `linked_service_id` (`linked_service_id`);

--
-- Indexes for table `inventory_log`
--
ALTER TABLE `inventory_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_identifier` (`identifier`),
  ADD KEY `idx_attempt_time` (`attempt_time`),
  ADD KEY `idx_success` (`success`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `staff_skills`
--
ALTER TABLE `staff_skills`
  ADD PRIMARY KEY (`staff_id`,`skill_id`),
  ADD KEY `skill_id` (`skill_id`);

--
-- Indexes for table `temporary_selections`
--
ALTER TABLE `temporary_selections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `appointment_date` (`appointment_date`),
  ADD KEY `expires_at` (`expires_at`),
  ADD KEY `identifier` (`identifier`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `fk_testimonials_staff` (`staff_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inventory_log`
--
ALTER TABLE `inventory_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=344;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=164;

--
-- AUTO_INCREMENT for table `skills`
--
ALTER TABLE `skills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `temporary_selections`
--
ALTER TABLE `temporary_selections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=394;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`linked_service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_log`
--
ALTER TABLE `inventory_log`
  ADD CONSTRAINT `inventory_log_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_log_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_skills`
--
ALTER TABLE `staff_skills`
  ADD CONSTRAINT `staff_skills_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_skills_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD CONSTRAINT `fk_testimonials_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `testimonials_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `testimonials_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
