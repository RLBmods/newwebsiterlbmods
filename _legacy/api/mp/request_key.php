<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../db/connection.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

try {
    // Verify authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated', 401);
    }

    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input', 400);
    }

    // Validate required fields
    $required = ['product', 'purpose', 'details'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field", 400);
        }
    }

    $product = sanitizeInput($input['product']);
    $purpose = sanitizeInput($input['purpose']);
    $details = sanitizeInput($input['details']);
    $userId = $_SESSION['user_id'];

    // Check request limits
    $currentWeek = date('Y-m-d', strtotime('monday this week'));
    $currentMonth = date('Y-m-01');

    // Weekly count
    $weeklyQuery = $con->prepare("SELECT COUNT(*) FROM license_keys 
                                WHERE user_id = ? AND product = ? AND created_at >= ?");
    $weeklyQuery->bind_param("iss", $userId, $product, $currentWeek);
    $weeklyQuery->execute();
    $weeklyCount = $weeklyQuery->get_result()->fetch_row()[0];

    // Monthly count
    $monthlyQuery = $con->prepare("SELECT COUNT(*) FROM license_keys 
                                 WHERE user_id = ? AND product = ? AND created_at >= ?");
    $monthlyQuery->bind_param("iss", $userId, $product, $currentMonth);
    $monthlyQuery->execute();
    $monthlyCount = $monthlyQuery->get_result()->fetch_row()[0];

    if ($weeklyCount >= 3) {
        throw new Exception("You've reached your weekly limit of 3 key requests for this product", 429);
    }

    if ($monthlyCount >= 12) {
        throw new Exception("You've reached your monthly limit of 12 key requests for this product", 429);
    }

    // Generate key
    $keyValue = 'RLB-MP-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(2)));

    // Insert into database
    $stmt = $con->prepare("INSERT INTO license_keys 
                          (user_id, key_value, product, purpose, details, status, expires_at) 
                          VALUES (?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 30 DAY))");
    $stmt->bind_param("isssss", $userId, $keyValue, $product, $purpose, $details);

    if (!$stmt->execute()) {
        throw new Exception("Failed to create license key: " . $con->error, 500);
    }

    // Log activity
    logActivity($userId, 'key', "Requested license key for $product");

    // Return success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'License key requested successfully',
        'data' => [
            'id' => $stmt->insert_id,
            'key_value' => $keyValue,
            'product' => $product,
            'status' => 'pending',
            'created_at' => date('c')
        ]
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}