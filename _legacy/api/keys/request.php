<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/init.php';

try {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) throw new Exception('User not authenticated');

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('Invalid input');
    
    $db = Database::getInstance();
    
    // First check limits
    $limitsStmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) THEN 1 ELSE 0 END) as weekly_count,
            SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) as monthly_count
        FROM license_keys 
        WHERE user_id = ?
    ");
    $limitsStmt->bind_param("i", $userId);
    $limitsStmt->execute();
    $limits = $limitsStmt->get_result()->fetch_assoc();
    
    if ($limits['weekly_count'] >= 3) {
        throw new Exception('Weekly limit of 3 keys reached');
    }
    
    if ($limits['monthly_count'] >= 12) {
        throw new Exception('Monthly limit of 12 keys reached');
    }
    
    // Generate key
    $keyValue = 'RLB-MP-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(2)));
    
    // Insert new key
    $stmt = $db->prepare("
        INSERT INTO license_keys 
        (user_id, key_value, product, purpose, details, status, expires_at) 
        VALUES (?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 30 DAY))
    ");
    $stmt->bind_param("issss", 
        $userId,
        $keyValue,
        $input['product'],
        $input['purpose'],
        $input['details']
    );
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Key requested successfully',
        'key' => [
            'id' => $stmt->insert_id,
            'key_value' => $keyValue,
            'product' => $input['product'],
            'status' => 'pending'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}