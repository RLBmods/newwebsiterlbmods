<?php
require_once '../../db/connection.php';
require_once '../../includes/logging.php';

header('Content-Type: application/json');

function authenticateReseller($con) {
    // Get the Authorization header
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    // Check if bearer token exists
    if (empty($authHeader)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authorization header missing']);
        exit;
    }
    
    // Extract the token
    $token = str_replace('Bearer ', '', $authHeader);
    
    // Validate token
    $stmt = $con->prepare("SELECT * FROM reseller_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $tokenData = $result->fetch_assoc();
    
    if (!$tokenData) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
        exit;
    }
    
    // Get reseller info
    $stmt = $con->prepare("SELECT * FROM usertable WHERE id = ? AND role = 'reseller'");
    $stmt->bind_param("i", $tokenData['user_id']);
    $stmt->execute();
    $reseller = $stmt->get_result()->fetch_assoc();
    
    if (!$reseller) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Reseller account not found or invalid']);
        exit;
    }
    
    return [
        'user_id' => $reseller['id'],
        'user_name' => $reseller['username'],
        'user_email' => $reseller['email'],
        'balance' => $reseller['balance'],
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
    ];
}
?>