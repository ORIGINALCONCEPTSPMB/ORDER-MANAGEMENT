<?php
// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

// Application
define('APP_NAME', 'Order Management System');
define('APP_URL', 'https://yourdomain.com');
define('APP_SECRET', 'change-this-to-random-32-char-string');

// Email (SMTP)
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'noreply@example.com');
define('MAIL_PASSWORD', 'your-email-password');
define('MAIL_FROM_NAME', 'Order Management System');
define('MAIL_FROM_EMAIL', 'noreply@example.com');
define('MAIL_ENCRYPTION', 'tls');

// Security
define('SESSION_LIFETIME', 7200);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900);
define('CODE_EXPIRY', 600);
define('RESET_TOKEN_EXPIRY', 3600);

// ProcessFlow Settings
define('WA_DEFAULT_COUNTRY', '27'); // Default WhatsApp country code (South Africa)
define('COMPANY_NAME', 'Your Company');
define('IN_URL', ''); // InvoiceNinja URL
define('IN_TOKEN', ''); // InvoiceNinja API token
