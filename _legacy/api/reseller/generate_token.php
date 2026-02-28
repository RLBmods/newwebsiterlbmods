<?php
require_once '../../db/connection.php';
require_once '../../includes/session.php';
require_once '../../includes/logging.php';
requireAuth();

// Set headers
header("Access-Control-Allow-Origin: https://s.compilecrew.xyz");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Debug logging
error_log("GENERATE_TOKEN.PHP - Session ID: " . session_id());
error_log("GENERATE_TOKEN.PHP - User ID: " . ($_SESSION['user_id'] ?? 'null'));

// Allowed roles
$allowedRoles = ['reseller', 'support', 'developer', 'manager', 'founder'];

if (!in_array($_SESSION['user_role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to generate API tokens']);
    exit;
}

// Generate token
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+1 year'));

// Check if token exists for this user
$checkStmt = $con->prepare("SELECT id FROM reseller_tokens WHERE user_id = ?");
$checkStmt->bind_param("i", $_SESSION['user_id']);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows > 0) {
    // Update existing token
    $stmt = $con->prepare("UPDATE reseller_tokens SET token = ?, expires_at = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $token, $expiresAt, $_SESSION['user_id']);
} else {
    // Insert new token if none exists
    $stmt = $con->prepare("INSERT INTO reseller_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $_SESSION['user_id'], $token, $expiresAt);
}

if ($stmt->execute()) {
    logAction($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SERVER['REMOTE_ADDR'], "Generated API token");
    echo json_encode([
        'success' => true,
        'token' => $token,
        'expires_at' => $expiresAt
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to generate token: ' . $con->error]);
}
?>