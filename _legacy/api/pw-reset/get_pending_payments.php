<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../db/connection.php';
require_once '../../includes/session.php';

// Enhanced logging setup
$logFile = '/var/www/customer/rlbmods-design/logs/pending_payments.log';
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

$response = ['success' => false, 'pending_payments' => [], 'error' => null];

try {
    logMessage("=== STARTING PENDING PAYMENTS CHECK ===");
    
    // Validate session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access", 401);
    }
    
    $userId = $_SESSION['user_id'];
    logMessage("User authenticated", ['user_id' => $userId]);

    // Get pending payments from the last 24 hours
    $stmt = $con->prepare("SELECT 
        order_id, 
        amount, 
        payment_method, 
        created_at,
        status,
        sell_key
    FROM payment_transactions 
    WHERE user_id = ? AND status = 'pending'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $con->error, 500);
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pendingPayments = [];
    while ($row = $result->fetch_assoc()) {
        $pendingPayments[] = $row;
    }
    
    logMessage("Found pending payments", [
        'count' => count($pendingPayments),
        'sample' => count($pendingPayments) > 0 ? $pendingPayments[0] : null
    ]);

    $response = [
        'success' => true,
        'pending_payments' => $pendingPayments,
        'count' => count($pendingPayments)
    ];
    
    logMessage("Pending payments check completed successfully");

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