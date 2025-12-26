<?php
/**
 * LUMIRA Configuration
 * Database and site settings
 */

// Database configuration
define('DB_HOST', 'localhost');  // Updated to local PostgreSQL
define('DB_PORT', '5432');
define('DB_NAME', 'lumira');  // Lowercase database name
define('DB_USER', 'postgres');
define('DB_PASS', '10_Jime_10');  // Updated to match homelab password

// Admin panel password
define('ADMIN_PASS', 'Admin@2025!');

// Site configuration
define('SITE_NAME', 'LUMIRA');
define('SITE_TAGLINE', 'Professional IT Solutions');
define('SITE_URL', 'http://10.0.1.100');
define('SITE_EMAIL', 'support@lumira.local');

// Email configuration (SMTP) - MailEnable
define('SMTP_HOST', 'localhost');  // MailEnable on same server
define('SMTP_PORT', 25);  // Standard SMTP port
define('SMTP_USERNAME', 'noreply');  // Username only (no domain)
define('SMTP_PASSWORD', 'Strongpassword123');  // MailEnable account password
define('SMTP_FROM_EMAIL', 'noreply@lumira.local');  // Must match authenticated user
define('SMTP_FROM_NAME', 'LUMIRA Support');
define('SMTP_ENCRYPTION', '');  // No encryption needed for localhost
define('SUPPORT_EMAIL', 'support@lumira.local');  // Support email address
define('NOTIFICATIONS_EMAIL', 'notifications@lumira.local');  // Central notifications inbox

// PayPal Sandbox Configuration
define('PAYPAL_MODE', 'sandbox'); // 'sandbox' or 'live'
define('PAYPAL_CLIENT_ID', 'AQoU5tnT37qrw2eDmf3_xTrRaCeXghnej_f8uqpATbbtElkrH145BTWQgR9e4xuXAW0HbtiBk7V21RUK');
define('PAYPAL_CLIENT_SECRET', 'ELpeR4Q6M4nZ3mQT4oQM9VgvWpxhZvloImlv5Zlbf-yuCRRcSS_1Kf9PM895fTl-NxNb46AUEgMql4Ow');
define('PAYPAL_API_BASE', PAYPAL_MODE === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
