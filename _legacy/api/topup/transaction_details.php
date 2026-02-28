<?php
//api/topup/transaction_details.php

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

    if (!isset($_GET['id'])) {
        throw new Exception("Transaction ID is required", 400);
    }

    $userId = $_SESSION['user_id'];
    $transactionId = $_GET['id'];

    // Get transaction details
    $stmt = $con->prepare("
        SELECT * FROM payment_transactions 
        WHERE id = ? AND user_id = ?
    ");
    
    $stmt->bind_param("ii", $transactionId, $userId);
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
        throw new Exception("Transaction not found", 404);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>