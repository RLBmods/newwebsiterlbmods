<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db/connection.php';
require_once __DIR__ . '/../../includes/session.php';

$response = ['success' => false, 'transactions' => []];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized", 401);
    }

    // Get transactions from both payment_transactions and transaction_history tables
    $userId = $_SESSION['user_id'];
    
    // Get crypto transactions
    $stmt = $con->prepare("
        SELECT 
            id, 
            order_id, 
            amount, 
            payment_method, 
            status, 
            created_at,
            'crypto' as type
        FROM payment_transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $cryptoResult = $stmt->get_result();
    
    $cryptoTransactions = [];
    while ($row = $cryptoResult->fetch_assoc()) {
        $cryptoTransactions[] = $row;
    }
    
    // Get card transactions (from transaction_history)
    $stmt = $con->prepare("
        SELECT 
            id, 
            order_id, 
            amount, 
            'card' as payment_method, 
            status, 
            created_at,
            'card' as type
        FROM transaction_history 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $cardResult = $stmt->get_result();
    
    $cardTransactions = [];
    while ($row = $cardResult->fetch_assoc()) {
        $cardTransactions[] = $row;
    }
    
    // Merge and sort transactions
    $allTransactions = array_merge($cryptoTransactions, $cardTransactions);
    usort($allTransactions, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Limit to 10 most recent transactions
    $response['transactions'] = array_slice($allTransactions, 0, 10);
    $response['success'] = true;

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit;