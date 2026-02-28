<?php
// Clear all output buffers and set headers first
while (ob_get_level()) ob_end_clean();

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include required files
require_once '../../config.php';
require_once '../../db/connection.php';
require_once '../../includes/session.php';

// Initialize response
$response = ['success' => false, 'error' => null];

// Safe logging function that won't break the script
function logMessage($message, $data = null) {
    $logDir = '../../api/logs/';
    $logFile = $logDir . 'nowpayments_create.log';
    
    // Try to create directory if it doesn't exist
    if (!file_exists($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    // Only log if we can write to the file
    if (file_exists($logDir) && is_writable($logDir)) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message";
        if ($data !== null) {
            $logEntry .= "\n" . json_encode($data, JSON_PRETTY_PRINT);
        }
        $logEntry .= "\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

try {
    logMessage("=== NOWPAYMENTS PAYMENT CREATION STARTED ===");
    
    // Validate session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access - please login", 401);
    }

    $userId = $_SESSION['user_id'];
    logMessage("User authenticated", ['user_id' => $userId]);

    // Check for existing pending transaction
    $stmt = $con->prepare("SELECT id FROM payment_transactions 
                         WHERE user_id = ? AND status = 'pending' 
                         AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                         LIMIT 1");
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $con->error, 500);
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        throw new Exception("You already have a pending transaction. Please complete it first.", 400);
    }

    // Get and validate input
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception("No data received", 400);
    }
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON: " . json_last_error_msg(), 400);
    }
    
    $amount = floatval($data['amount'] ?? 0);
    $quantity = intval($data['quantity'] ?? 1);
    $method = $data['paymentMethod'] ?? '';
    $note = $data['note'] ?? '';
    
    if ($amount < 1 || $amount > 1000) {
        throw new Exception("Amount must be between $1 and $1000", 400);
    }
    
    if (empty($method)) {
        throw new Exception("Payment method is required", 400);
    }

    logMessage("Validated input", [
        'amount' => $amount,
        'quantity' => $quantity,
        'method' => $method,
        'note' => $note
    ]);

    // Get user data
    $stmt = $con->prepare("SELECT id, name, email, current_ip FROM usertable WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $con->error, 500);
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        throw new Exception("User not found", 404);
    }
    
    logMessage("Retrieved user data", ['user_id' => $user['id'], 'email' => $user['email']]);

    // Generate unique order ID
    $orderId = 'NP_' . uniqid() . '_' . time();
    
    // Prepare NowPayments PAYMENT endpoint payload
    $payload = [
        'price_amount' => $amount,
        'price_currency' => 'usd',
        'pay_currency' => $method,
        'ipn_callback_url' => NOWPAYMENTS_WEBHOOK_URL,
        'order_id' => $orderId,
        'order_description' => $note ?: 'Balance topup for ' . $user['email'],
        'customer_email' => $user['email']
    ];
    
    logMessage("Prepared NowPayments payload", $payload);

    // Make API request to NowPayments
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.nowpayments.io/v1/payment",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . NOWPAYMENTS_API_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'RLBmods/1.0')
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    logMessage("NowPayments API response", [
        'http_code' => $httpCode,
        'curl_error' => $curlError,
        'response' => $res
    ]);

    if (!$res) {
        throw new Exception("Empty response from NowPayments API: " . $curlError, 500);
    }

    $resData = json_decode($res, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from NowPayments", 500);
    }

    if ($httpCode !== 201 && $httpCode !== 200) {
        $errorMsg = $resData['message'] ?? "NowPayments API error: HTTP $httpCode";
        if (isset($resData['reason'])) {
            $errorMsg .= " - Reason: " . $resData['reason'];
        }
        throw new Exception($errorMsg, $httpCode);
    }

    // Extract payment details
    $paymentId = $resData['payment_id'] ?? '';
    $paymentStatus = $resData['payment_status'] ?? 'waiting';
    $paymentAddress = $resData['pay_address'] ?? '';
    $payAmount = $resData['pay_amount'] ?? 0;
    $payCurrency = $resData['pay_currency'] ?? $method;
    $priceAmount = $resData['price_amount'] ?? $amount;
    $priceCurrency = $resData['price_currency'] ?? 'usd';
    $expirationDate = $resData['expiration_estimate_date'] ?? null;
    
    if (empty($paymentAddress)) {
        throw new Exception("No payment address received from NowPayments", 500);
    }
    
    logMessage("Payment details extracted", [
        'payment_id' => $paymentId,
        'payment_address' => $paymentAddress,
        'pay_amount' => $payAmount,
        'pay_currency' => $payCurrency,
        'expiration_date' => $expirationDate
    ]);

    // Create checkout URL for reference
    $checkoutUrl = "https://nowpayments.io/payment/?iid=" . $paymentId;
    
    // First, let's check the database structure
    $checkColumns = $con->query("DESCRIBE payment_transactions");
    $columns = [];
    while ($row = $checkColumns->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    logMessage("Database columns found", $columns);
    
    // Save transaction to database - FIXED VERSION
    // Using only the essential columns that definitely exist
    $stmt = $con->prepare("INSERT INTO payment_transactions 
        (user_id, order_id, amount, status, checkout_url, payment_method, crypto_address, gateway, transaction_id)
        VALUES (?, ?, ?, 'pending', ?, ?, ?, 'nowpayments', ?)");
    
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $con->error, 500);
    }
    
    $stmt->bind_param("isdisss", 
        $user['id'],
        $orderId,
        $amount,
        $checkoutUrl,
        $method,
        $paymentAddress,
        $paymentId
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Database error: " . $stmt->error, 500);
    }
    
    $dbTransactionId = $con->insert_id;
    
    logMessage("Transaction saved to database", [
        'db_id' => $dbTransactionId,
        'order_id' => $orderId
    ]);

    // Prepare success response
    $response = [
        'success' => true,
        'data' => [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'checkout_url' => $checkoutUrl,
            'payment_address' => $paymentAddress,
            'payment_amount' => $payAmount,
            'payment_currency' => $payCurrency,
            'price_amount' => $priceAmount,
            'price_currency' => $priceCurrency,
            'gateway' => 'nowpayments',
            'transaction_id' => $paymentId,
            'status' => $paymentStatus,
            'note' => $note,
            'network' => $resData['network'] ?? $method,
            'expiration_estimate_date' => $expirationDate,
            'db_transaction_id' => $dbTransactionId
        ]
    ];

    logMessage("Payment creation completed successfully");

} catch (Exception $e) {
    // Clean any output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set HTTP status code
    if (!headers_sent()) {
        http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    }
    
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ];
    
    logMessage("ERROR: " . $e->getMessage(), [
        'code' => $e->getCode()
    ]);
}

// Final output
echo json_encode($response);
exit;