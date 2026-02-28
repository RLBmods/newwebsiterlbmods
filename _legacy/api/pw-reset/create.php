<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../db/connection.php';
require_once '../../includes/session.php';

// Enhanced logging
$logFile = '/var/www/customer/rlbmods-design/logs/create.log';
$logDir = dirname($logFile);
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMessage($message, $data = null) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    if ($data !== null) {
        $logEntry .= "\n" . json_encode($data, JSON_PRETTY_PRINT);
    }
    $logEntry .= "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

$response = ['success' => false, 'error' => null];

try {
    logMessage("=== STARTING PAYMENT CREATION ===");
    
    // Validate session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access", 401);
    }
    
    $userId = $_SESSION['user_id'];
    logMessage("User authenticated", ['user_id' => $userId]);

    // Check for existing pending transaction
    $stmt = $con->prepare("SELECT id FROM payment_transactions 
                         WHERE user_id = ? AND status = 'pending' 
                         AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                         LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        throw new Exception("You already have a pending transaction. Please complete it first.", 400);
    }

    // Validate input
    $raw = file_get_contents("php://input");
    if (!$raw) throw new Exception("Empty request body", 400);
    
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON input", 400);
    }
    
    $amount = floatval($data['amount'] ?? 0);
    $quantity = intval($data['quantity'] ?? 0);
    $method = $data['paymentMethod'] ?? 'btc';
    $note = $data['note'] ?? '';
    
    if ($amount < 1 || $amount > 1000) {
        throw new Exception("Amount must be between $1 and $1000", 400);
    }
    
    logMessage("Validated input", [
        'amount' => $amount,
        'quantity' => $quantity,
        'method' => $method,
        'note' => $note
    ]);

    // Get user data
    $stmt = $con->prepare("SELECT id, name, email, current_ip FROM usertable WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) throw new Exception("User not found", 404);
    
    logMessage("Retrieved user data", ['user_id' => $user['id'], 'email' => $user['email']]);

    // Prepare API payload
    $payload = [
        'customer' => [
            'name' => $user['name'],
            'email' => $user['email'],
            'address' => 'Not Provided'
        ],
        'paymentMethod' => $method,
        'ipAddress' => $user['current_ip'],
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0',
        'emailAddress' => $user['email'],
        'webhookUrl' => SELLSN_WEBHOOK_URL,
        'ProductIds' => [
            ['id' => SELLSN_PRODUCT_ID, 'quantity' => $amount]
        ],
        'note' => $note
    ];
    
    logMessage("Prepared SellSN payload", $payload);

    // Make API request
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.sellsn.io/stores/" . SELLSN_STORE_ID . "/orders",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SELLSN_BEARER_TOKEN,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0')
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    logMessage("SellSN API response", [
        'http_code' => $httpCode,
        'curl_error' => $curlError,
        'response' => $res
    ]);

    if (!$res) throw new Exception("Empty response from API: " . $curlError, 500);

    $resData = json_decode($res, true);
    if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("Invalid JSON response", 500);

    if ($httpCode !== 200 || !isset($resData['data']['checkoutUrl'])) {
        throw new Exception($resData['message'] ?? "Unknown API error", $httpCode);
    }

    // Extract order details
    $checkoutUrl = $resData['data']['checkoutUrl'];
    $orderId = basename($checkoutUrl);
    $order = $resData['data']['order'];
    $key = $resData['data']['order']['key'] ?? null;
    
    logMessage("Received order details", [
        'order_id' => $orderId,
        'checkout_url' => $checkoutUrl,
        'key' => $key
    ]);

    // Save transaction to database
    $stmt = $con->prepare("INSERT INTO payment_transactions 
        (user_id, order_id, amount, quantity, status, checkout_url, payment_method, 
         crypto_address, gateway, transaction_id, note, sell_key)
        VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("isdisssssss",
        $user['id'],
        $orderId,
        $amount,
        $quantity,
        $checkoutUrl,
        $method,
        $order['cryptoAddress'] ?? null,
        $order['gateway'] ?? null,
        $order['transactionId'] ?? null,
        $note,
        $key
    );
    
    $stmt->execute();
    $transactionId = $con->insert_id;
    
    logMessage("Transaction saved", [
        'transaction_id' => $transactionId,
        'order_id' => $orderId
    ]);

    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'order_id' => $orderId,
            'checkout_url' => $checkoutUrl,
            'payment_address' => $order['cryptoAddress'] ?? null,
            'payment_amount' => $order['cryptoAmount'] ?? $amount,
            'payment_currency' => $order['cryptoCurrency'] ?? 'USD',
            'gateway' => $order['gateway'] ?? null,
            'transaction_id' => $order['transactionId'] ?? null,
            'paypal_email' => $order['paypalEmail'] ?? 'mreagle13337@gmail.com',
            'paypal_note' => $key,
        ]
    ];

    logMessage("Payment creation completed successfully");

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
    logMessage("ERROR: " . $e->getMessage(), [
        'code' => $e->getCode(),
        'trace' => $e->getTraceAsString()
    ]);
}

logMessage("Final response", $response);
echo json_encode($response);
exit;