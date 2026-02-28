<?php
require_once '../../../config.php';
require_once '../../../includes/session.php';
require_once '../../../db/connection.php';

header('Content-Type: application/json');

// Start session and verify client key
requireAuth();
requireMember();

// Verify client key
// Replace this section in payment_handler.php:

// Verify client key
$headers = getallheaders();

// Check if Client-Key header exists
if (!isset($headers['Client-Key'])) {
    // Also check for alternate header names or query parameters
    $clientKey = $_SERVER['HTTP_CLIENT_KEY'] ?? $_GET['client_key'] ?? null;
    
    if (!$clientKey) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Client-Key header required']);
        exit;
    }
} else {
    $clientKey = $headers['Client-Key'];
}

// Now verify the key
if ($clientKey !== PAYTABS_CLIENT_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid Client-Key']);
    exit;
}
// Get database connection
$db = $pdo; // From connection.php

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? ($input['action'] ?? '');

    switch ($action) {
        case 'create_payment_page':
            $response = handleCreatePaymentPage($input, $db);
            break;
            
        case 'check_payment_status':
            $response = handleCheckPaymentStatus($db);
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
            http_response_code(400);
    }

    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => DEBUG_MODE ? $e->getTrace() : null
    ]);
}

function handleCreatePaymentPage($request, $db) {
    // Validate request
    if (!isset($request['amount']) || !is_numeric($request['amount']) || $request['amount'] <= 0) {
        throw new Exception('Invalid amount specified');
    }

    // Get user data
    $stmt = $db->prepare("SELECT id, name, email FROM usertable WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    // Generate a unique order ID
    $orderId = 'PT_' . time() . '_' . uniqid();
    
    // Create transaction record
    $stmt = $db->prepare("
        INSERT INTO payment_transactions 
        (user_id, order_id, amount, payment_method, status, created_at, updated_at)
        VALUES (?, ?, ?, 'card', 'pending', NOW(), NOW())
    ");
    $stmt->execute([$user['id'], $orderId, $request['amount']]);
    $transactionId = $db->lastInsertId();

    // Prepare PayTabs request
    $payload = [
        'profile_id' => PAYTABS_PROFILE_ID,
        'tran_type' => 'sale',
        'tran_class' => 'ecom',
        'cart_id' => $transactionId,
        'cart_description' => $request['productDescription'] ?? 'Balance Top-Up',
        'cart_currency' => $request['currency'] ?? 'USD',
        'cart_amount' => $request['amount'],
        'customer_details' => [
            'name' => $user['name'],
            'email' => $user['email']
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
        // Update transaction status to failed
        $stmt = $db->prepare("
            UPDATE payment_transactions 
            SET status = 'failed', updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$transactionId]);
        
        throw new Exception('Payment gateway error: HTTP ' . $httpCode);
    }
    
    $responseData = json_decode($result, true);
    if (!$responseData || !isset($responseData['redirect_url'])) {
        throw new Exception('Invalid response from payment gateway');
    }
    
    // Update transaction with PayTabs transaction reference and checkout URL
    $stmt = $db->prepare("
        UPDATE payment_transactions 
        SET transaction_id = ?, checkout_url = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$responseData['tran_ref'] ?? '', $responseData['redirect_url'], $transactionId]);
    
    return [
        'success' => true,
        'data' => [
            'redirectUrl' => $responseData['redirect_url'],
            'transactionId' => $transactionId,
            'orderId' => $orderId,
            'paytabsRef' => $responseData['tran_ref'] ?? ''
        ]
    ];
}

function handleCheckPaymentStatus($db) {
    if (!isset($_SESSION['user_id'])) {
        return ['success' => false, 'message' => 'User not authenticated'];
    }

    // Get most recent pending card transaction
    $stmt = $db->prepare("
        SELECT id, order_id, amount, status, transaction_id, created_at
        FROM payment_transactions 
        WHERE user_id = ? AND payment_method = 'card'
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        return ['success' => false, 'message' => 'No transaction found'];
    }

    // For frontend to display current status
    return [
        'success' => true,
        'data' => [
            'status' => $transaction['status'],
            'transactionId' => $transaction['id'],
            'orderId' => $transaction['order_id'],
            'amount' => $transaction['amount'],
            'paytabsRef' => $transaction['transaction_id']
        ]
    ];
}