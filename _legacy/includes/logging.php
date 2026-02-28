<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../db/connection.php';

/**
 * Ensure session variables are always set to prevent undefined errors.
 */
function sanitizeSessionVariables() {
    $session_keys = ['user_id', 'user_name', 'user_email'];

    foreach ($session_keys as $key) {
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = null;
        }
    }
}

// Sanitize session variables before logging any action
sanitizeSessionVariables();

/**
 * Fetch username from database by user ID
 */
function fetchUsernameById($user_id) {
    global $con;
    
    if (empty($user_id)) return null;
    
    try {
        $stmt = $con->prepare("SELECT name FROM usertable WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            return $user['name'];
        }
        return null;
    } catch (Exception $e) {
        error_log("Failed to fetch username: " . $e->getMessage());
        return null;
    }
}

/**
 * Log user actions into the database with enhanced details
 */
function logAction(
    $user_id = null,
    $username = null,
    $email = null,
    $ip = null,
    $action = "Unknown Action",
    $action_type = "system",
    $details = null,
    $additional_data = null,
    $status = "success"
) {
    global $con;

    // Set default values if not provided
    $user_id = $user_id ?? $_SESSION['user_id'] ?? 0;
    $username = $username ?? $_SESSION['user_name'] ?? "System";
    $email = $email ?? $_SESSION['user_email'] ?? "system@rlbmods.com";
    $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? "0.0.0.0";
    $details = $details ?? $action;
    
    // Validate and sanitize action type
    $valid_action_types = ['login', 'admin', 'subscription', 'ban', 'system', 'user', 'security'];
    $action_type = in_array(strtolower($action_type), $valid_action_types) ? strtolower($action_type) : 'system';
    
    // Validate status
    $valid_statuses = ['success', 'failed', 'warning', 'pending', 'blocked'];
    $status = in_array(strtolower($status), $valid_statuses) ? strtolower($status) : 'success';
    
    // Prepare additional data
    $additional_json = null;
    if ($additional_data !== null) {
        $additional_json = json_encode($additional_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $additional_json = json_encode(['error' => 'Failed to encode additional data', 'raw' => $additional_data]);
        }
    }

    try {
        $stmt = $con->prepare("INSERT INTO logs 
            (user_id, username, email, ip_address, action_type, action, details, additional_data, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "issssssss",
            $user_id,
            $username,
            $email,
            $ip,
            $action_type,
            $action,
            $details,
            $additional_json,
            $status
        );

        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Failed to log action: " . $e->getMessage());
        return false;
    }
}

/**
 * Helper function to log security events
 */
function logSecurityEvent($action, $details = null, $additional_data = null, $status = 'warning', $username = null, $email = null) {
    $user_id = is_array($additional_data) ? ($additional_data['user_id'] ?? null) : null;
    
    // Get username in order of priority:
    // 1. Explicitly passed username
    // 2. From database if user_id exists
    // 3. From additional_data if available
    // 4. Default "Security System"
    $log_username = $username ?? fetchUsernameById($user_id);
    
    if ($log_username === null && is_array($additional_data)) {
        $log_username = $additional_data['username'] ?? $additional_data['name'] ?? null;
    }
    $log_username = $log_username ?? 'Security System';

    // Get email in order of priority:
    // 1. Explicitly passed email
    // 2. From additional_data
    // 3. Default security email
    $log_email = $email;
    if ($log_email === null && is_array($additional_data)) {
        $log_email = $additional_data['email'] ?? null;
    }
    $log_email = $log_email ?? 'security@rlbmods.com';

    return logAction(
        $user_id,
        $log_username,
        $log_email,
        $_SERVER['REMOTE_ADDR'] ?? "0.0.0.0",
        $action,
        'security',
        $details ?? "Security event: " . $action,
        $additional_data,
        $status
    );
}

/**
 * Helper function to log admin actions
 */
function logAdminAction($action, $target = null, $details = null, $additional_data = null) {
    $log_details = "Admin action: " . $action;
    if ($target) {
        $log_details .= " (Target: " . $target . ")";
    }
    
    return logAction(
        $_SESSION['user_id'] ?? 0,
        $_SESSION['user_name'] ?? "Admin",
        $_SESSION['user_email'] ?? "admin@rlbmods.com",
        $_SERVER['REMOTE_ADDR'] ?? "0.0.0.0",
        $action,
        'admin',
        $details ?? $log_details,
        $additional_data,
        'success'
    );
}

/**
 * Helper function to log user actions
 */
function logUserAction($user_id, $action, $details = null, $additional_data = null, $status = 'success') {
    return logAction(
        $user_id,
        fetchUsernameById($user_id) ?? "User $user_id",
        null,
        $_SERVER['REMOTE_ADDR'] ?? "0.0.0.0",
        $action,
        'user',
        $details ?? "User action: " . $action,
        $additional_data,
        $status
    );
}