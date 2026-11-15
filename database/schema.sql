-- ApexSMM Panel MySQL 8+ / MariaDB 10.6+ Schema
-- Normalized 20-table relational schema with strict foreign keys & indexes

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `smm_panel` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `smm_panel`;

-- 1. users
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(60) NOT NULL,
    `full_name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `balance` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `total_spent` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `api_key` VARCHAR(64) NULL DEFAULT NULL,
    `api_status` TINYINT(1) NOT NULL DEFAULT 1,
    `status` ENUM('active', 'suspended', 'banned') NOT NULL DEFAULT 'active',
    `timezone` VARCHAR(64) NOT NULL DEFAULT 'Asia/Kolkata',
    `failed_login_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL DEFAULT NULL,
    `last_login_at` DATETIME NULL DEFAULT NULL,
    `last_login_ip` VARCHAR(45) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email` (`email`),
    UNIQUE KEY `uq_users_api_key` (`api_key`),
    KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. admin_roles
CREATE TABLE IF NOT EXISTS `admin_roles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(80) NOT NULL,
    `slug` VARCHAR(80) NOT NULL,
    `description` VARCHAR(255) NULL DEFAULT NULL,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admin_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. admin_permissions
CREATE TABLE IF NOT EXISTS `admin_permissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` INT UNSIGNED NOT NULL,
    `permission_key` VARCHAR(100) NOT NULL,
    `allowed` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_role_permission` (`role_id`, `permission_key`),
    CONSTRAINT `fk_admin_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `admin_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. admins
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` INT UNSIGNED NOT NULL,
    `username` VARCHAR(60) NOT NULL,
    `full_name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `failed_login_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL DEFAULT NULL,
    `last_login_at` DATETIME NULL DEFAULT NULL,
    `last_login_ip` VARCHAR(45) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admins_username` (`username`),
    UNIQUE KEY `uq_admins_email` (`email`),
    KEY `idx_admins_role` (`role_id`),
    CONSTRAINT `fk_admins_role` FOREIGN KEY (`role_id`) REFERENCES `admin_roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. categories
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `slug` VARCHAR(140) NOT NULL,
    `platform` VARCHAR(60) NOT NULL DEFAULT 'general',
    `description` TEXT NULL,
    `default_margin_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
    `default_margin_value` DECIMAL(10,4) NOT NULL DEFAULT 30.0000,
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_slug` (`slug`),
    KEY `idx_categories_status_sort` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. providers
CREATE TABLE IF NOT EXISTS `providers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `api_url` VARCHAR(255) NOT NULL,
    `api_key` VARCHAR(255) NOT NULL,
    `api_type` ENUM('standard_v2', 'custom') NOT NULL DEFAULT 'standard_v2',
    `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
    `exchange_rate` DECIMAL(12,6) NOT NULL DEFAULT 1.000000,
    `balance` DECIMAL(14,4) NULL DEFAULT NULL,
    `balance_checked_at` DATETIME NULL DEFAULT NULL,
    `connection_status` ENUM('unverified', 'connected', 'error') NOT NULL DEFAULT 'unverified',
    `last_error` VARCHAR(500) NULL DEFAULT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_providers_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. services
CREATE TABLE IF NOT EXISTS `services` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED NOT NULL,
    `provider_id` INT UNSIGNED NULL DEFAULT NULL,
    `provider_service_id` VARCHAR(80) NULL DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `service_type` ENUM('default', 'custom_comments', 'package', 'drip_feed') NOT NULL DEFAULT 'default',
    `provider_rate` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `margin_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
    `margin_value` DECIMAL(10,4) NOT NULL DEFAULT 30.0000,
    `rate_per_1000` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `min_qty` INT UNSIGNED NOT NULL DEFAULT 10,
    `max_qty` INT UNSIGNED NOT NULL DEFAULT 100000,
    `refill_supported` TINYINT(1) NOT NULL DEFAULT 0,
    `cancel_supported` TINYINT(1) NOT NULL DEFAULT 0,
    `dripfeed_supported` TINYINT(1) NOT NULL DEFAULT 0,
    `average_time` VARCHAR(80) NULL DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `last_synced_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_services_category_status` (`category_id`, `status`),
    KEY `idx_services_provider` (`provider_id`, `provider_service_id`),
    CONSTRAINT `fk_services_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_services_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. provider_service_mappings
CREATE TABLE IF NOT EXISTS `provider_service_mappings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider_id` INT UNSIGNED NOT NULL,
    `service_id` INT UNSIGNED NOT NULL,
    `external_service_id` VARCHAR(80) NOT NULL,
    `external_service_name` VARCHAR(255) NOT NULL,
    `external_category` VARCHAR(160) NULL DEFAULT NULL,
    `external_rate` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `external_min` INT UNSIGNED NOT NULL DEFAULT 10,
    `external_max` INT UNSIGNED NOT NULL DEFAULT 100000,
    `auto_sync_price` TINYINT(1) NOT NULL DEFAULT 1,
    `last_synced_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_provider_ext_service` (`provider_id`, `external_service_id`),
    UNIQUE KEY `uq_mapping_service` (`service_id`),
    CONSTRAINT `fk_psm_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_psm_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. orders
CREATE TABLE IF NOT EXISTS `orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_code` VARCHAR(32) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `service_id` INT UNSIGNED NOT NULL,
    `provider_id` INT UNSIGNED NULL DEFAULT NULL,
    `provider_order_id` VARCHAR(100) NULL DEFAULT NULL,
    `link` VARCHAR(1000) NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `rate_per_1000` DECIMAL(14,4) NOT NULL,
    `charge` DECIMAL(14,4) NOT NULL,
    `provider_cost` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `profit` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `start_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `remains` INT UNSIGNED NOT NULL DEFAULT 0,
    `custom_comments` TEXT NULL,
    `status` ENUM('pending', 'processing', 'in_progress', 'completed', 'partial', 'cancelled', 'refunded', 'failed') NOT NULL DEFAULT 'pending',
    `refunded_amount` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `is_refunded` TINYINT(1) NOT NULL DEFAULT 0,
    `idempotency_key` VARCHAR(64) NULL DEFAULT NULL,
    `provider_response` TEXT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orders_code` (`order_code`),
    UNIQUE KEY `uq_orders_idempotency` (`user_id`, `idempotency_key`),
    KEY `idx_orders_user_status` (`user_id`, `status`),
    KEY `idx_orders_status_created` (`status`, `created_at`),
    KEY `idx_orders_provider` (`provider_id`, `provider_order_id`),
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_orders_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_orders_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. order_logs
CREATE TABLE IF NOT EXISTS `order_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `old_status` VARCHAR(40) NULL DEFAULT NULL,
    `new_status` VARCHAR(40) NOT NULL,
    `message` VARCHAR(500) NOT NULL,
    `actor_type` ENUM('system', 'user', 'admin', 'cron', 'provider') NOT NULL DEFAULT 'system',
    `actor_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_logs_order` (`order_id`),
    CONSTRAINT `fk_order_logs_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. wallet_transactions
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tx_code` VARCHAR(36) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `type` ENUM('deposit', 'order_charge', 'refund', 'admin_credit', 'admin_debit', 'bonus') NOT NULL,
    `amount` DECIMAL(14,4) NOT NULL,
    `balance_before` DECIMAL(14,4) NOT NULL,
    `balance_after` DECIMAL(14,4) NOT NULL,
    `reference_type` VARCHAR(40) NULL DEFAULT NULL,
    `reference_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `description` VARCHAR(255) NOT NULL,
    `created_by_admin_id` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wallet_tx_code` (`tx_code`),
    KEY `idx_wallet_tx_user_created` (`user_id`, `created_at`),
    KEY `idx_wallet_tx_ref` (`reference_type`, `reference_id`),
    CONSTRAINT `fk_wallet_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_wallet_tx_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. payment_methods
CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `instructions` TEXT NOT NULL,
    `min_amount` DECIMAL(14,4) NOT NULL DEFAULT 10.0000,
    `max_amount` DECIMAL(14,4) NOT NULL DEFAULT 100000.0000,
    `fee_percent` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `bonus_percent` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `requires_proof` TINYINT(1) NOT NULL DEFAULT 1,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payment_methods_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. payments
CREATE TABLE IF NOT EXISTS `payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_code` VARCHAR(36) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `method_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(14,4) NOT NULL,
    `fee_amount` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `bonus_amount` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `net_credit` DECIMAL(14,4) NOT NULL,
    `transaction_ref` VARCHAR(160) NOT NULL,
    `proof_file` VARCHAR(255) NULL DEFAULT NULL,
    `user_note` TEXT NULL,
    `admin_note` TEXT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `processed_by_admin_id` INT UNSIGNED NULL DEFAULT NULL,
    `processed_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payments_code` (`payment_code`),
    KEY `idx_payments_user_status` (`user_id`, `status`),
    KEY `idx_payments_status` (`status`),
    CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_payments_method` FOREIGN KEY (`method_id`) REFERENCES `payment_methods` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_payments_admin` FOREIGN KEY (`processed_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. support_tickets
CREATE TABLE IF NOT EXISTS `support_tickets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_code` VARCHAR(32) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `department` ENUM('order', 'payment', 'api', 'general') NOT NULL DEFAULT 'general',
    `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    `status` ENUM('open', 'answered', 'closed') NOT NULL DEFAULT 'open',
    `last_reply_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tickets_code` (`ticket_code`),
    KEY `idx_tickets_user_status` (`user_id`, `status`),
    CONSTRAINT `fk_tickets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tickets_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. ticket_messages
CREATE TABLE IF NOT EXISTS `ticket_messages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_id` BIGINT UNSIGNED NOT NULL,
    `sender_type` ENUM('user', 'admin') NOT NULL,
    `sender_id` BIGINT UNSIGNED NOT NULL,
    `message` TEXT NOT NULL,
    `attachment` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ticket_messages_ticket` (`ticket_id`),
    CONSTRAINT `fk_ticket_messages_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. notifications
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `admin_id` INT UNSIGNED NULL DEFAULT NULL,
    `title` VARCHAR(160) NOT NULL,
    `message` VARCHAR(500) NOT NULL,
    `type` ENUM('info', 'success', 'warning', 'danger') NOT NULL DEFAULT 'info',
    `link` VARCHAR(255) NULL DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_user_read` (`user_id`, `is_read`),
    KEY `idx_notifications_admin_read` (`admin_id`, `is_read`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notifications_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. announcements
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(180) NOT NULL,
    `content` TEXT NOT NULL,
    `type` ENUM('update', 'promo', 'maintenance', 'notice') NOT NULL DEFAULT 'notice',
    `is_published` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by_admin_id` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_announcements_pub` (`is_published`, `created_at`),
    CONSTRAINT `fk_announcements_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. settings
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT NULL,
    `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. activity_logs
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_type` ENUM('user', 'admin', 'system', 'cron') NOT NULL,
    `actor_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `action` VARCHAR(120) NOT NULL,
    `entity_type` VARCHAR(60) NULL DEFAULT NULL,
    `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_actor` (`actor_type`, `actor_id`),
    KEY `idx_activity_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. api_logs
CREATE TABLE IF NOT EXISTS `api_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider_id` INT UNSIGNED NULL DEFAULT NULL,
    `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `direction` ENUM('outbound_provider', 'inbound_user') NOT NULL,
    `endpoint` VARCHAR(255) NOT NULL,
    `action` VARCHAR(80) NOT NULL,
    `request_payload` MEDIUMTEXT NULL,
    `response_payload` MEDIUMTEXT NULL,
    `http_code` INT UNSIGNED NULL DEFAULT NULL,
    `duration_ms` INT UNSIGNED NULL DEFAULT NULL,
    `is_success` TINYINT(1) NOT NULL DEFAULT 0,
    `error_message` VARCHAR(500) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_api_logs_provider` (`provider_id`, `created_at`),
    KEY `idx_api_logs_user` (`user_id`, `created_at`),
    CONSTRAINT `fk_api_logs_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_api_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
