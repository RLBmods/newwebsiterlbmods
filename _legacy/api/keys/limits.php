<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/init.php';

try {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) throw new Exception('User not authenticated');

    $db = Database::getInstance();
    
    // Get weekly count (since Monday)
    $weeklyStmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM license_keys 
        WHERE user_id = ? 
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
    ");
    $weeklyStmt->bind_param("i", $userId);
    $weeklyStmt->execute();
    $weeklyCount = $weeklyStmt->get_result()->fetch_assoc()['count'];
    
    // Get monthly count (since 1st of current month)
    $monthlyStmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM license_keys 
        WHERE user_id = ? 
        AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    ");
    $monthlyStmt->bind_param("i", $userId);
    $monthlyStmt->execute();
    $monthlyCount = $monthlyStmt->get_result()->fetch_assoc()['count'];

    echo json_encode([
        'success' => true,
        'weekly_count' => (int)$weeklyCount,
        'monthly_count' => (int)$monthlyCount,
        'weekly_limit' => 3,
        'monthly_limit' => 12
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}