<?php
// Include the session management file
require_once './includes/session.php';

// Ensure the session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verify CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'])) {
        error_log("CSRF token missing during logout attempt");
        header("HTTP/1.1 403 Forbidden");
        die("Invalid request");
    }
    
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        error_log("CSRF token mismatch during logout attempt");
        header("HTTP/1.1 403 Forbidden");
        die("Invalid request");
    }
}

// Get the session ID
$session_id = session_id();

// Delete the session from the database
if (!empty($session_id)) {
    global $con;
    $stmt = $con->prepare("DELETE FROM sessions WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("s", $session_id);
        if (!$stmt->execute()) {
            error_log("Failed to delete session from database: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare statement for session deletion: " . $con->error);
    }
} else {
    error_log("No session ID found during logout.");
}

// Regenerate CSRF token before destroying session (for subsequent logins)
if (isset($_SESSION['csrf_token'])) {
    unset($_SESSION['csrf_token']);
}

// Destroy the session
$_SESSION = []; // Clear all session variables

// If cookie-based session, delete the cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"], 
        $params["secure"], 
        $params["httponly"]
    );
}

session_destroy();

// Redirect to login page
header("Location: /login.php");
exit();
?>