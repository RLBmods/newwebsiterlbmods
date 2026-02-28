<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../db/connection.php';
require_once '../includes/get_user_info.php';
require_once 'cheats/auth.php';
require_once 'cheats/credentials.php';

if (!isset($_SESSION['user_id'])) {
    die('<script type="text/javascript">
        const notyf = new Notyf();
        notyf.error({ message: "You must be logged in.", duration: 3500, dismissible: true });
    </script>');
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['user_name'];
$license_key = trim($_POST['license_key'] ?? '');

// Initialize KeyAuth
$KeyAuthApp = new KeyAuth\api($name, $OwnerId, $secret);
$KeyAuthApp->init();

// Check if user has redeemed any license before
$stmt_check = $con->prepare("SELECT id FROM user_activated WHERE user_id = ?");
$stmt_check->bind_param("i", $user_id);
$stmt_check->execute();
$stmt_check->store_result();
$has_redeemed = $stmt_check->num_rows > 0;

try {
    if ($has_redeemed) {
        // Already redeemed before → upgrade
        $success = $KeyAuthApp->upgrade($username, $license_key);

        if ($success) {
            echo '<script type="text/javascript">
                const notyf = new Notyf();
                notyf.success({ message: "License upgraded successfully!", duration: 3500, dismissible: true });
            </script>';
        }
    } else {
        // First time → register
        $password_input = trim($_POST['password'] ?? '');
        if (!$password_input) {
            die('<script type="text/javascript">
                const notyf = new Notyf();
                notyf.error({ message: "Password is required for first activation.", duration: 3500, dismissible: true });
            </script>');
        }

        $success = $KeyAuthApp->register($username, $password_input, $license_key);

        if ($success) {
            // Insert into DB
            $stmt_insert = $con->prepare("INSERT INTO user_activated (user_id, license_key, activated_at) VALUES (?, ?, NOW())");
            $stmt_insert->bind_param("is", $user_id, $license_key);
            $stmt_insert->execute();

            echo '<script type="text/javascript">
                const notyf = new Notyf();
                notyf.success({ message: "License activated successfully!", duration: 3500, dismissible: true });
            </script>';
        }
    }

} catch (\Exception $e) {
    $KeyAuthApp->error("An unexpected error occurred: " . $e->getMessage());
}
