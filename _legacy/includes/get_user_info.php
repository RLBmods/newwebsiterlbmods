<?php
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/session.php';

global $con;

// Get user ID from session
$user_id = intval($_SESSION['user_id'] ?? 0);

// Initialize variables with default values
$username = 'Guest';
$email = '';
$role = 'guest';
$usrstatus = '';
$usrbalance = 0;
$discordid = null;
$current_ip = '';
$last_ip = '';
$profile_picture = '/assets/avatars/default-avatar.png'; // Default avatar path

// Only proceed if we have a valid user ID
if ($user_id > 0) {
    // Prepare and execute query to fetch user information
    $stmt = $con->prepare("SELECT name, email, role, balance, discordid, status, current_ip, last_ip, profile_picture FROM usertable WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            // Fetch user data
            $user = $result->fetch_assoc();
            $username = $user['name'] ?? 'Guest';
            $email = $user['email'] ?? '';
            $role = $user['role'] ?? 'guest';
            $usrstatus = $user['status'] ?? '';
            $usrbalance = $user['balance'] ?? 0;
            $discordid = $user['discordid'] ?? null;
            $current_ip = $user['current_ip'] ?? '';
            $last_ip = $user['last_ip'] ?? '';
            $profile_picture = $user['profile_picture'] ?? '/assets/avatars/default-avatar.png';
            
            // Update session variables
            $_SESSION['user_name'] = $username;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = $role;
            $_SESSION['profile_picture'] = $profile_picture;
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare SQL statement: " . $con->error);
    }
}


function isAdmin($user_id = null) {
    // If no user_id provided, check current session
    if ($user_id === null) {
        return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['manager', 'founder']);
    }
    
    // If user_id provided, check database
    global $con;
    
    $stmt = $con->prepare("SELECT role FROM usertable WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        return in_array($user['role'], ['manager', 'founder']);
    }
    
    return false;
}
?>