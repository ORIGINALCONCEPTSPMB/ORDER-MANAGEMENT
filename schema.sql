-- Order Management System Database Schema (ProcessFlow Manager)
-- Version: 2.0.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('super_admin','admin','user') NOT NULL DEFAULT 'user',
  `first_name` VARCHAR(100) NOT NULL DEFAULT '',
  `last_name` VARCHAR(100) NOT NULL DEFAULT '',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: verification_codes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `verification_codes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `code` VARCHAR(6) NOT NULL,
  `type` ENUM('registration','password_reset','login_2fa') NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`),
  CONSTRAINT `fk_vc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: sessions
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
  `user_agent` TEXT,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_sess_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: pf_orders
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pf_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_name` VARCHAR(255) NOT NULL DEFAULT '',
  `business_name` VARCHAR(255) NOT NULL DEFAULT '',
  `whatsapp` VARCHAR(50) NOT NULL DEFAULT '',
  `invoice_number` VARCHAR(100) NOT NULL DEFAULT '',
  `job_details` TEXT NOT NULL,
  `product_lines` LONGTEXT NULL,
  `current_stage` INT UNSIGNED NULL,
  `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
  `archived_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `qr_code_hash` VARCHAR(64) NOT NULL DEFAULT '',
  `custom_fields` LONGTEXT NULL,
  `created_by` INT NULL,
  PRIMARY KEY (`id`),
  KEY `current_stage` (`current_stage`),
  KEY `qr_code_hash` (`qr_code_hash`),
  KEY `is_archived` (`is_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: pf_stages
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pf_stages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `order_position` INT NOT NULL DEFAULT 0,
  `color` VARCHAR(20) NOT NULL DEFAULT '#3498db',
  `whatsapp_template` TEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `order_position` (`order_position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: pf_stage_history
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pf_stage_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `stage_id` INT UNSIGNED NOT NULL,
  `entered_at` DATETIME NOT NULL,
  `completed_at` DATETIME NULL,
  `notification_sent` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `stage_id` (`stage_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: pf_custom_fields
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pf_custom_fields` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `field_label` VARCHAR(255) NOT NULL DEFAULT '',
  `field_type` ENUM('text','textarea','number','date','checkbox') NOT NULL DEFAULT 'text',
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `field_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: pf_settings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pf_settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: password_resets
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Default super_admin user
-- IMPORTANT SECURITY NOTICE:
-- This row uses a known default password (Admin@123!) and is intended ONLY
-- as a fallback when the schema is imported manually without the installation
-- wizard. The installation wizard (install.php) always replaces this hash
-- with the password you supply during setup.
-- If you import this file directly, you MUST change the password on first
-- login and update the email address to a real address you control.
-- --------------------------------------------------------
INSERT INTO `users` (`email`, `password_hash`, `role`, `first_name`, `last_name`, `is_active`, `is_verified`)
VALUES (
  'admin@example.com',
  '$2y$12$NOUqSvkGmNsUMBqg6dl.A.pvalFcDEw63aVdVyWuaPp3t1ZMXnGTC',
  'super_admin',
  'Super',
  'Admin',
  1,
  1
);

-- --------------------------------------------------------
-- Default workflow stages
-- --------------------------------------------------------
INSERT IGNORE INTO `pf_stages` (`name`, `order_position`, `color`, `whatsapp_template`, `is_active`) VALUES
('In Design', 1, '#3498db', 'Hi {customer_name}, your order #{order_id} for {business_name} is currently In Design. We will notify you when it progresses.', 1),
('In Print Queue', 2, '#e67e22', 'Hi {customer_name}, your order #{order_id} for {business_name} is now In the Print Queue. We are getting it ready!', 1),
('In Finishing', 3, '#9b59b6', 'Hi {customer_name}, your order #{order_id} for {business_name} is In Finishing. Almost there!', 1),
('Ready for Collection', 4, '#27ae60', 'Hi {customer_name}, great news! Your order #{order_id} for {business_name} is Ready for Collection. Please come collect at your earliest convenience.', 1);

-- --------------------------------------------------------
-- Default settings
-- --------------------------------------------------------
INSERT IGNORE INTO `pf_settings` (`setting_key`, `setting_value`) VALUES
('company_name', 'Your Company'),
('company_phone', ''),
('company_email', ''),
('company_logo_url', ''),
('portal_title', 'Track Your Order'),
('portal_intro', 'Enter your order number or invoice number to track your order.'),
('portal_track_mode', 'order_id_only'),
('orders_per_page', '20'),
('enable_whatsapp', '1'),
('wa_default_country', '27'),
('invoiceninja_url', ''),
('invoiceninja_token', ''),
('order_fields_enabled', '{"business_name":true,"whatsapp":true,"invoice_number":true,"job_details":true,"product_lines":true}'),
('order_fields_required', '{"whatsapp":true,"invoice_number":false,"job_details":false,"product_lines":false}');
