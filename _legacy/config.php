<?php

// SellSN API Configuration
define('NOWPAYMENTS_API_KEY', 'WEMBR6Z-QY541W1-MXZZQC0-GPH8B74');
define('NOWPAYMENTS_WEBHOOK_URL', 'https://panel.rlbmods.com/api/webhook/nowpayments.php');
define('NOWPAYMENTS_SUCCESS_URL', 'https://panel.rlbmods.com/payment/success');
define('NOWPAYMENTS_CANCEL_URL', 'https://panel.rlbmods.com/payment/cancel');
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u620238184_rlbmodsauth');
define('DB_PASS', 'Donistrash@2024');
define('DB_NAME', 'u620238184_rlbmodsauth');

// Other configuration
define('SITE_URL', 'https://rlbmods.com');
define('DEBUG_MODE', true);

// PayTabs Configuration
define('PAYTABS_PROFILE_ID', '164477');
define('PAYTABS_SERVER_KEY', 'S2J92J6HTZ-JLJZZZNLZD-9HLHBRDBZ6');
define('PAYTABS_CLIENT_KEY', 'CMK2Q9-96QP6B-6999QB-PMD27D'); // Replace with actual client key
define('PAYTABS_API_URL', 'https://secure.paytabs.com');
define('PAYTABS_WEBHOOK_URL', 'https://rlbmods.com/panel/api/topup/card/webhook_handler.php');
define('PAYTABS_RETURN_URL', 'https://rlbmods.com/panel/paymennt-complete.php');


session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

// Error reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set timezone
date_default_timezone_set('UTC');