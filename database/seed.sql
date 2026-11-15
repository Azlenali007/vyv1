-- ApexSMM Panel Structural Seed Data
-- Contains ONLY system configuration, roles, permissions, default admin login, and payment method templates.
-- NO fake users, NO fake orders, NO fake revenue, NO fake transactions.

USE `smm_panel`;

-- Default Admin Roles
INSERT IGNORE INTO `admin_roles` (`id`, `name`, `slug`, `description`, `is_system`) VALUES
(1, 'Super Administrator', 'super_admin', 'Full unrestricted access to all panel modules, providers, finances, and settings.', 1),
(2, 'Operations Manager', 'manager', 'Manage orders, services, categories, providers, and support tickets.', 1),
(3, 'Support Agent', 'support_agent', 'View orders and respond to user support tickets.', 1);

-- Default Role Permissions
INSERT IGNORE INTO `admin_permissions` (`role_id`, `permission_key`, `allowed`) VALUES
(1, 'dashboard.view', 1),
(1, 'users.manage', 1),
(1, 'users.balance', 1),
(1, 'services.manage', 1),
(1, 'categories.manage', 1),
(1, 'providers.manage', 1),
(1, 'orders.manage', 1),
(1, 'payments.manage', 1),
(1, 'tickets.manage', 1),
(1, 'announcements.manage', 1),
(1, 'reports.view', 1),
(1, 'settings.manage', 1),
(1, 'admins.manage', 1),
(1, 'logs.view', 1),
(1, 'backup.manage', 1),

(2, 'dashboard.view', 1),
(2, 'users.manage', 1),
(2, 'services.manage', 1),
(2, 'categories.manage', 1),
(2, 'providers.manage', 1),
(2, 'orders.manage', 1),
(2, 'tickets.manage', 1),
(2, 'announcements.manage', 1),
(2, 'reports.view', 1),

(3, 'dashboard.view', 1),
(3, 'orders.manage', 1),
(3, 'tickets.manage', 1);

-- Initial Super Admin Account (Default password: Admin@12345 - change immediately after login)
INSERT IGNORE INTO `admins` (`id`, `role_id`, `username`, `full_name`, `email`, `password_hash`, `status`) VALUES
(1, 1, 'admin', 'Panel Administrator', 'admin@apexsmm.local', '$2y$10$X6q7q20y5ZtkiU7nFHMIX.CrCm5EPtC7zNy/FO.fyPz06BIwpAVmm', 'active');

-- Default System Settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
('site_name', 'SMM Panel', 'general'),
('site_tagline', 'Automated Social Media Marketing Infrastructure', 'general'),
('support_email', 'support@apexsmm.local', 'general'),
('currency_symbol', '₹', 'currency'),
('currency_code', 'INR', 'currency'),
('default_margin_type', 'percentage', 'pricing'),
('default_margin_value', '30', 'pricing'),
('min_deposit_amount', '10', 'payments'),
('registration_enabled', '1', 'security'),
('maintenance_mode', '0', 'security'),
('maintenance_message', 'Scheduled maintenance is underway. Please check back shortly.', 'security'),
('api_enabled', '1', 'api'),
('terms_updated_at', '2026-11-15', 'legal');

-- Structural Manual Payment Methods (awaiting operator's real bank/UPI details in Admin -> Settings/Payments)
INSERT IGNORE INTO `payment_methods` (`id`, `name`, `code`, `instructions`, `min_amount`, `max_amount`, `fee_percent`, `bonus_percent`, `requires_proof`, `status`, `sort_order`) VALUES
(1, 'UPI / QR Transfer (Awaiting Setup)', 'upi_manual', 'Operator Note: Configure your real UPI ID or QR instructions in Admin Panel before accepting live payments.', 50.0000, 100000.0000, 0.00, 0.00, 1, 'active', 1),
(2, 'Bank Transfer / NEFT / IMPS (Awaiting Setup)', 'bank_transfer', 'Operator Note: Configure your real Bank Account Number, IFSC, and Account Name in Admin Panel.', 500.0000, 500000.0000, 0.00, 0.00, 1, 'active', 2);
