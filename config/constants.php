<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('CLASSES_PATH', BASE_PATH . '/classes');
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('PAYMENTS_UPLOAD_PATH', UPLOADS_PATH . '/payments');
define('TICKETS_UPLOAD_PATH', UPLOADS_PATH . '/tickets');
define('DATABASE_PATH', BASE_PATH . '/database');

define('APP_NAME_DEFAULT', 'ApexSMM Panel');
define('APP_VERSION', '1.0.0');
define('DEFAULT_CURRENCY_SYMBOL', '₹');
define('DEFAULT_CURRENCY_CODE', 'INR');

// Order statuses
define('ORDER_STATUS_PENDING', 'pending');
define('ORDER_STATUS_PROCESSING', 'processing');
define('ORDER_STATUS_IN_PROGRESS', 'in_progress');
define('ORDER_STATUS_COMPLETED', 'completed');
define('ORDER_STATUS_PARTIAL', 'partial');
define('ORDER_STATUS_CANCELLED', 'cancelled');
define('ORDER_STATUS_REFUNDED', 'refunded');
define('ORDER_STATUS_FAILED', 'failed');

// Payment statuses
define('PAYMENT_STATUS_PENDING', 'pending');
define('PAYMENT_STATUS_APPROVED', 'approved');
define('PAYMENT_STATUS_REJECTED', 'rejected');

// Ticket statuses
define('TICKET_STATUS_OPEN', 'open');
define('TICKET_STATUS_ANSWERED', 'answered');
define('TICKET_STATUS_CLOSED', 'closed');

// Wallet transaction types
define('TX_TYPE_DEPOSIT', 'deposit');
define('TX_TYPE_ORDER_CHARGE', 'order_charge');
define('TX_TYPE_REFUND', 'refund');
define('TX_TYPE_ADMIN_CREDIT', 'admin_credit');
define('TX_TYPE_ADMIN_DEBIT', 'admin_debit');
define('TX_TYPE_BONUS', 'bonus');

// Upload constraints
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGE_MIMES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('ALLOWED_TICKET_MIMES', ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'text/plain']);

// Security limits
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 900); // 15 minutes
define('CSRF_TOKEN_TTL', 7200); // 2 hours
