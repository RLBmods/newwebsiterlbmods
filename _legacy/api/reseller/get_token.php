<?php
require_once '../../db/connection.php';
require_once '../../includes/session.php';
requireAuth();

// Set headers
header("Access-Control-Allow-Origin: https://s.compilecrew.xyz");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Debug logging
error_log("GET_TOKEN.PHP - Session ID: " . session_id());
error_log("GET_TOKEN.PHP - User ID: " . ($_SESSION['user_id'] ?? 'null'));

// Allowed roles
$allowedRoles = ['reseller', 'support', 'developer', 'manager', 'founder'];

if (!in_array($_SESSION['user_role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to view API tokens']);
    exit;
}

$stmt = $con->prepare("SELECT token, expires_at FROM reseller_tokens WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $tokenData = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'token' => $tokenData['token'],
        'expires_at' => $tokenData['expires_at']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No API token found']);
}
?>