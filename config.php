<?php
// config/config.php

// Site Configuration
define('SITE_NAME', 'SCAN EAT REPEAT');
define('SITE_URL', 'http://localhost/qr-restaurant-system');
define('ADMIN_EMAIL', 'admin@restaurant.com');

// Directory Paths
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('QR_DIR', UPLOAD_DIR . 'qr_codes/');
define('MENU_IMG_DIR', UPLOAD_DIR . 'menu_images/');
define('LOGO_DIR', UPLOAD_DIR . 'logos/');

// Create directories if they don't exist
$dirs = [UPLOAD_DIR, QR_DIR, MENU_IMG_DIR, LOGO_DIR];
foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS

// Payment Gateway Configuration
// VISA Card (Paystack for Ghana)
define('PAYSTACK_PUBLIC_KEY', 'pk_test_a8f0df5d8a4a09f0a21e61c57f09c23aa28f424b'); // Replace with your public key
define('PAYSTACK_SECRET_KEY', 'sk_test_fcda943551ef151bbbf72dae112114f8f6086939'); // Replace with your secret key

// Mobile Money (MTN, Vodafone, AirtelTigo)
define('MOMO_API_KEY', 'your_momo_api_key'); // Replace with your Mobile Money API key
define('MOMO_API_SECRET', 'your_momo_api_secret'); // Replace with your secret

// Currency
define('CURRENCY', 'GHS');
define('CURRENCY_SYMBOL', '₵');

// Timezone
date_default_timezone_set('Africa/Accra');

// Error Reporting (Disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session Start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}