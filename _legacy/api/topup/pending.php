<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../db/connection.php';
require_once '../../includes/session.php';

$response = ['success' => false, 'pending_transactions' => []];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access", 401);
    }
    
    $userId = $_SESSION['user_id'];
    
    // Get pending crypto transactions
    $stmt = $con->prepare("
        SELECT * FROM payment_transactions 
        WHERE user_id = ? AND status = 'pending' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pendingTransactions = [];
    while ($row = $result->fetch_assoc()) {
        $row['amount'] = (float)$row['amount'];
        $pendingTransactions[] = $row;
    }
    
    // Get pending card transactions
    $stmt = $con->prepare("
        SELECT 
            id, 
            order_id, 
            amount, 
            'card' as payment_method, 
            status, 
            created_at
        FROM transaction_history 
        WHERE user_id = ? AND status = 'pending' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $cardResult = $stmt->get_result();
    
    while ($row = $cardResult->fetch_assoc()) {
        $row['amount'] = (float)$row['amount'];
        $pendingTransactions[] = $row;
    }
    
    $response = [
        'success' => true,
        'pending_transactions' => $pendingTransactions
    ];
    
} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit;