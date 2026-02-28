<?php
require_once __DIR__ . '../../includes/session.php';
require_once __DIR__ . '../../includes/functions.php';
require_once __DIR__ . '../../db/connection.php';
require_once __DIR__ . '../../includes/classes/Validator.php';
require_once __DIR__ . '../../includes/classes/Response.php';
require_once __DIR__ . '../../includes/classes/Stream.php';
require_once __DIR__ . '../../includes/classes/LicenseKey.php';
require_once __DIR__ . '../../includes/classes/ActivityLogger.php';

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple Auth class
class Auth {
    public static function check(): bool {
        return isset($_SESSION['user_id']);
    }
}