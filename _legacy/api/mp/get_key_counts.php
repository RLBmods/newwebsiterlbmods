<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../db/connection.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated', 401);
    }

    if (!isset($_GET['product'])) {
        throw new Exception('Product parameter is required', 400);
    }

    $product = sanitizeInput($_GET['product']);
    $userId = $_SESSION['user_id'];
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

    echo json_encode([
        'success' => true,
        'data' => [
            'weekly_count' => (int)$weeklyCount,
            'monthly_count' => (int)$monthlyCount,
            'product' => $product
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