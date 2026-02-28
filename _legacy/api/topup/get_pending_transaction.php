<?php
//api/topup/get_pending_transaction.php

header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../db/connection.php';
require_once '../../includes/session.php';

$response = ['success' => false, 'transaction' => null, 'error' => null];

try {
    // Validate session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access", 401);
    }

    $userId = $_SESSION['user_id'];

    // Get the most recent pending transaction for this user
    $stmt = $con->prepare("
        SELECT * FROM payment_transactions 
        WHERE user_id = ? 
        AND status IN ('pending') 
        AND gateway = 'nowpayments'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();

    if ($transaction) {
        // Decode API data if it exists
        if ($transaction['api_data']) {
            $transaction['api_data'] = json_decode($transaction['api_data'], true);
        }
        
        $response = [
            'success' => true,
            'transaction' => $transaction
        ];
    } else {
        $response = [
            'success' => true,
            'transaction' => null
        ];
    }

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>