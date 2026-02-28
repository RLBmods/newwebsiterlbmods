<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../db/connection.php';
require_once '../../includes/session.php';

// Enhanced logging setup
$logFile = '/var/www/customer/rlbmods-design/logs/status.log';
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
    logMessage("=== STARTING STATUS CHECK ===");
    
    // Validate session and input
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access", 401);
    }

    $orderId = $_GET['order_id'] ?? null;
    $source = $_GET['source'] ?? 'local'; // Default to local check
    if (empty($orderId)) {
        throw new Exception("Order ID is required", 400);
    }
    
    logMessage("Status check initiated", [
        'order_id' => $orderId,
        'source' => $source,
        'user_id' => $_SESSION['user_id'],
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);

    // 1. Check local database first
    $stmt = $con->prepare("SELECT * FROM payment_transactions 
                          WHERE order_id = ? AND user_id = ?");
    $stmt->bind_param("si", $orderId, $_SESSION['user_id']);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();

    if (!$transaction) {
        throw new Exception("Transaction not found", 404);
    }
    
    logMessage("Local transaction found", [
        'current_status' => $transaction['status'],
        'amount' => $transaction['amount'],
        'created_at' => $transaction['created_at']
    ]);

    // Return immediately if already completed and we're not forcing a SellSN check
    if ($transaction['status'] === 'completed' && $source !== 'sellsn') {
        $balance = getCurrentBalance($_SESSION['user_id']);
        logMessage("Transaction already completed", ['balance' => $balance]);
        
        $response = [
            'success' => true,
            'status' => 'completed',
            'current_balance' => $balance,
            'synced' => false
        ];
        echo json_encode($response);
        exit;
    }

    // 2. Always verify with SellSN API when requested
    $apiResponse = checkSellSnStatus($orderId);
    $newStatus = determineStatus($apiResponse);
    
    // Additional verification for delivered items
    $deliveredContent = $apiResponse['data']['order']['deliveredItem'] ?? '';
    $isActuallyDelivered = !empty($deliveredContent) && 
                          stripos($deliveredContent, 'error') === false;
    
    if ($isActuallyDelivered) {
        $newStatus = 'completed';
        logMessage("Delivery content verification passed", [
            'delivered_item' => substr($deliveredContent, 0, 100) . '...'
        ]);
    }
    
    logMessage("Status determination", [
        'sellSN_status' => $apiResponse['data']['order']['status'] ?? null,
        'delivery_verified' => $isActuallyDelivered,
        'final_status' => $newStatus
    ]);

    // 3. Update local status if changed
    if ($transaction['status'] !== $newStatus) {
        updateTransactionStatus($orderId, $newStatus, $deliveredContent);
        
        // Special handling for completed status
        if ($newStatus === 'completed') {
            $balance = completeTransaction($transaction);
            $response['current_balance'] = $balance;
        }
    }

    // Prepare final response
    $response = [
        'success' => true,
        'status' => $newStatus,
        'status_message' => $apiResponse['data']['order']['status'] ?? null,
        'synced' => ($transaction['status'] !== $newStatus),
        'order_id' => $orderId,
        'delivery_verified' => $isActuallyDelivered,
        'current_balance' => getCurrentBalance($transaction['user_id'])
    ];

    logMessage("Status check completed successfully", $response);

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
    logMessage("ERROR: " . $e->getMessage(), [
        'code' => $e->getCode(),
        'trace' => $e->getTraceAsString()
    ]);
}

echo json_encode($response);
exit;

// Helper Functions

function checkSellSnStatus($orderId) {
    global $con, $logFile;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.sellsn.io/stores/" . SELLSN_STORE_ID . "/orders/" . $orderId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SELLSN_BEARER_TOKEN,
            'Accept: application/json',
            'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0')
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 15
    ]);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200 || !$res) {
        throw new Exception("SellSN API Error: $curlError", 500);
    }

    $data = json_decode($res, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid SellSN response", 500);
    }

    // Additional check for SellSN-specific error responses
    if (isset($data['error'])) {
        throw new Exception("SellSN API Error: " . $data['error']['message'], 500);
    }

    return $data;
}

function determineStatus($apiResponse) {
    $status = strtolower($apiResponse['data']['order']['status'] ?? 'pending');
    
    // SellSN specific status mapping
    $statusMap = [
        'paid' => 'completed',
        'completed' => 'completed',
        'delivered' => 'completed',
        'confirmed' => 'completed',
        'failed' => 'failed',
        'cancelled' => 'failed',
        'expired' => 'expired',
        'refunded' => 'failed',
        'pending' => 'pending',
        'waiting' => 'pending'
    ];

    // Check for exact matches first
    if (isset($statusMap[$status])) {
        return $statusMap[$status];
    }
    
    // Check for partial matches
    foreach ($statusMap as $key => $value) {
        if (stripos($status, $key) !== false) {
            return $value;
        }
    }
    
    // Default to pending if unknown status
    return 'pending';
}

function updateTransactionStatus($orderId, $status, $deliveredContent = null) {
    global $con;
    
    $stmt = $con->prepare("UPDATE payment_transactions 
                          SET status = ?, 
                              delivery_status = ?,
                              delivered_item = ?,
                              updated_at = NOW() 
                          WHERE order_id = ?");
    $deliveredStatus = ($status === 'completed') ? 'delivered' : null;
    $stmt->bind_param("ssss", $status, $deliveredStatus, $deliveredContent, $orderId);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Failed to update transaction status");
    }
}

function completeTransaction($transaction) {
    global $con;
    
    $con->begin_transaction();
    try {
        // 1. Update user balance
        $stmt = $con->prepare("UPDATE usertable 
                              SET balance = balance + ? 
                              WHERE id = ?");
        $stmt->bind_param("di", $transaction['amount'], $transaction['user_id']);
        $stmt->execute();
        
        // 2. Record in balance history
        $stmt = $con->prepare("INSERT INTO balance_history 
                              (user_id, amount, type, reference_id, payment_method, notes)
                              VALUES (?, ?, 'topup', ?, ?, ?)");
        $note = "Top-up via " . $transaction['payment_method'] . " (Order: " . $transaction['order_id'] . ")";
        $stmt->bind_param("idsss", 
            $transaction['user_id'],
            $transaction['amount'],
            $transaction['order_id'],
            $transaction['payment_method'],
            $note
        );
        $stmt->execute();
        
        $con->commit();
        
        return getCurrentBalance($transaction['user_id']);
        
    } catch (Exception $e) {
        $con->rollback();
        throw new Exception("Balance update failed: " . $e->getMessage());
    }
}

function getCurrentBalance($userId) {
    global $con;
    
    $stmt = $con->prepare("SELECT balance FROM usertable WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['balance'] ?? 0;
}