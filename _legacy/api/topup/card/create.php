<?php
require_once '../../../includes/session.php';
require_once '../../../db/connection.php';
require_once '../../../includes/logging.php';

header('Content-Type: application/json');

// Verify authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);
$amount = floatval($input['amount'] ?? 0);
$paymentMethod = $input['paymentMethod'] ?? '';

if ($amount <= 0 || $paymentMethod !== 'card') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid amount or payment method']);
    exit;
}

try {
    // Generate a unique order ID
    $orderId = 'CARD_' . time() . '_' . bin2hex(random_bytes(4));
    
    // Create transaction record
    $stmt = $con->prepare("
        INSERT INTO payment_transactions 
        (user_id, order_id, amount, payment_method, status, created_at) 
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->bind_param("isds", $_SESSION['user_id'], $orderId, $amount, $paymentMethod);
    $stmt->execute();
    
    // Use your existing card payment system directly
    $redirectUrl = getCardPaymentUrl($amount, $orderId);
    
    if (!$redirectUrl) {
        throw new Exception('Failed to create payment session');
    }
    
    // Return success with payment details
    echo json_encode([
        'success' => true,
        'data' => [
            'order_id' => $orderId,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'redirect_url' => $redirectUrl
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getCardPaymentUrl($amount, $orderId) {
    // This function will directly use your existing card payment system
    // without modifying the original payment_handler.php
    
    // Include your config
    require_once '../../../config.php';
    
    // Prepare the exact same payload that your working card system expects
    $payload = [
        'action' => 'create_payment_page',
        'amount' => $amount,
        'currency' => 'USD',
        'productDescription' => "Balance Top-Up ($$amount)",
        'cart_id' => $orderId
    ];
    
    // Make request to your existing payment handler
    $ch = curl_init('https://s.compilecrew.xyz/card/payment_handler.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Client-Key: ' . PAYTABS_CLIENT_KEY
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data['success'] && isset($data['data']['redirectUrl'])) {
            return $data['data']['redirectUrl'];
        }
    }
    
    return null;
}
?>