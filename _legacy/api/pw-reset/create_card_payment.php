<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../db/connection.php';
require_once '../../includes/session.php';

// Start session and verify client key
requireAuth();
requireMember();

$response = ['success' => false, 'message' => ''];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!isset($input['amount']) || !is_numeric($input['amount']) || $input['amount'] <= 0) {
        throw new Exception('Invalid amount specified');
    }
    
    if (!isset($input['user_id']) || !isset($input['email']) || !isset($input['name'])) {
        throw new Exception('Missing required user information');
    }
    
    $amount = floatval($input['amount']);
    $userId = intval($input['user_id']);
    $email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
    $name = filter_var($input['name'], FILTER_SANITIZE_STRING);
    
    // Create transaction record
    $stmt = $con->prepare("
        INSERT INTO payment_transactions 
        (user_id, amount, status, payment_method, created_at)
        VALUES (?, ?, 'pending', 'card', NOW())
    ");
    $stmt->bind_param("id", $userId, $amount);
    $stmt->execute();
    $transactionId = $con->insert_id;
    
    // Prepare PayTabs request
    $payload = [
        'profile_id' => PAYTABS_PROFILE_ID,
        'tran_type' => 'sale',
        'tran_class' => 'ecom',
        'cart_id' => $transactionId,
        'cart_description' => 'Balance Top-Up',
        'cart_currency' => 'USD',
        'cart_amount' => $amount,
        'customer_details' => [
            'name' => $name,
            'email' => $email
        ],
        'callback' => PAYTABS_WEBHOOK_URL,
        'return' => PAYTABS_RETURN_URL
    ];
    
    $ch = curl_init(PAYTABS_API_URL . '/payment/request');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . PAYTABS_SERVER_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Payment gateway error: HTTP ' . $httpCode);
    }
    
    $responseData = json_decode($result, true);
    if (!$responseData || !isset($responseData['redirect_url'])) {
        throw new Exception('Invalid response from payment gateway');
    }
    
    // Update transaction with order ID
    $stmt = $con->prepare("
        UPDATE payment_transactions 
        SET order_id = ?, gateway = 'paytabs'
        WHERE id = ?
    ");
    $stmt->bind_param("si", $responseData['tran_ref'], $transactionId);
    $stmt->execute();
    
    $response = [
        'success' => true,
        'redirect_url' => $responseData['redirect_url'],
        'transaction_id' => $transactionId
    ];
    
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);