<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../db/connection.php';
require_once '../../includes/session.php';

// Enhanced logging setup
$logFile = '/var/www/customer/rlbmods-design/logs/update_status.log';
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
    logMessage("=== STARTING PAYMENT STATUS UPDATE ===");
    
    // Validate session and input
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access", 401);
    }
    
    $raw = file_get_contents("php://input");
    if (!$raw) throw new Exception("Empty request body", 400);
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON input", 400);
    }
    
    $orderId = $data['order_id'] ?? null;
    $status = $data['status'] ?? null;
    $currentBalance = $data['current_balance'] ?? null;
    
    if (empty($orderId) || empty($status)) {
        throw new Exception("Missing required fields", 400);
    }
    
    logMessage("Update request received", [
        'order_id' => $orderId,
        'status' => $status,
        'user_id' => $_SESSION['user_id'],
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);

    // 1. Verify the transaction belongs to this user
    $stmt = $con->prepare("SELECT * FROM payment_transactions 
                          WHERE order_id = ? AND user_id = ?");
    $stmt->bind_param("si", $orderId, $_SESSION['user_id']);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();

    if (!$transaction) {
        throw new Exception("Transaction not found", 404);
    }
    
    logMessage("Transaction found", [
        'current_status' => $transaction['status'],
        'amount' => $transaction['amount'],
        'created_at' => $transaction['created_at']
    ]);

    // 2. Update transaction status
    $con->begin_transaction();
    try {
        $stmt = $con->prepare("UPDATE payment_transactions 
                              SET status = ?, 
                                  updated_at = NOW() 
                              WHERE order_id = ?");
        $stmt->bind_param("ss", $status, $orderId);
        $stmt->execute();
        
        // 3. If completed, update balance
        if ($status === 'completed') {
            $stmt = $con->prepare("UPDATE usertable 
                                  SET balance = balance + ? 
                                  WHERE id = ?");
            $stmt->bind_param("di", $transaction['amount'], $_SESSION['user_id']);
            $stmt->execute();
            
            // Record in balance history
            $stmt = $con->prepare("INSERT INTO balance_history 
                                  (user_id, amount, type, reference_id, payment_method, notes)
                                  VALUES (?, ?, 'topup', ?, ?, ?)");
            $note = "Status update via client: " . $status;
            $stmt->bind_param("idsss", 
                $_SESSION['user_id'],
                $transaction['amount'],
                $orderId,
                $transaction['payment_method'],
                $note
            );
            $stmt->execute();
        }
        
        $con->commit();
        
        logMessage("Transaction status updated successfully", [
            'new_status' => $status,
            'amount_added' => ($status === 'completed' ? $transaction['amount'] : 0)
        ]);
        
        $response = [
            'success' => true,
            'order_id' => $orderId,
            'new_status' => $status,
            'amount' => $transaction['amount'],
            'current_balance' => getCurrentBalance($_SESSION['user_id'])
        ];
        
    } catch (Exception $e) {
        $con->rollback();
        throw new Exception("Database update failed: " . $e->getMessage());
    }

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

function getCurrentBalance($userId) {
    global $con;
    
    $stmt = $con->prepare("SELECT balance FROM usertable WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['balance'] ?? 0;
}