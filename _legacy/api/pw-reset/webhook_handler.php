<?php
require_once '../../config.php';
require_once '../../db/connection.php';

// Enhanced logging setup
$logFile = '/var/www/customer/rlbmods-design/logs/webhook.log';
$logDir = dirname($logFile);
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

function logWebhook($message, $data = null) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    if ($data !== null) {
        $logEntry .= "\n" . json_encode($data, JSON_PRETTY_PRINT);
    }
    $logEntry .= "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

header('Content-Type: application/json');

try {
    // 1. Verify webhook signature
    $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);
    
    logWebhook("Webhook received", [
        'headers' => $_SERVER,
        'payload' => $data
    ]);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON payload", 400);
    }

    // 2. Validate required fields
    if (!isset($data['type']) || !isset($data['data']['order']['id'])) {
        throw new Exception("Missing required fields", 400);
    }

    $orderId = $data['data']['order']['id'];
    $eventType = $data['type'];
    $status = strtolower($data['data']['order']['status'] ?? 'pending');
    $deliveredItem = $data['data']['order']['deliveredItem'] ?? '';

    logWebhook("Processing webhook", [
        'order_id' => $orderId,
        'event_type' => $eventType,
        'status' => $status,
        'delivered_item' => substr($deliveredItem, 0, 100) . (strlen($deliveredItem) > 100 ? '...' : '')
    ]);

    // 3. Get transaction from database
    $stmt = $con->prepare("SELECT * FROM payment_transactions WHERE order_id = ?");
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();

    if (!$transaction) {
        throw new Exception("Transaction not found for order: $orderId", 404);
    }

    // 4. Determine new status with delivery verification
    $newStatus = 'pending';
    $isActuallyDelivered = !empty($deliveredItem) && stripos($deliveredItem, 'error') === false;
    
    if (stripos($status, 'delivered') !== false || stripos($status, 'completed') !== false) {
        $newStatus = $isActuallyDelivered ? 'completed' : 'pending';
    } elseif (stripos($status, 'fail') !== false || stripos($status, 'cancel') !== false) {
        $newStatus = 'failed';
    }

    logWebhook("Status determination", [
        'original_status' => $status,
        'delivery_verified' => $isActuallyDelivered,
        'new_status' => $newStatus
    ]);

    // 5. Update transaction status
    $con->begin_transaction();
    try {
        $stmt = $con->prepare("UPDATE payment_transactions 
                              SET status = ?, 
                                  delivery_status = ?,
                                  delivered_item = ?,
                                  updated_at = NOW() 
                              WHERE order_id = ?");
        $deliveryStatus = $isActuallyDelivered ? 'delivered' : null;
        $stmt->bind_param("ssss", $newStatus, $deliveryStatus, $deliveredItem, $orderId);
        $stmt->execute();

        // 6. Update balance if completed
        if ($newStatus === 'completed') {
            $stmt = $con->prepare("UPDATE usertable SET balance = balance + ? WHERE id = ?");
            $stmt->bind_param("di", $transaction['amount'], $transaction['user_id']);
            $stmt->execute();
            
            // Record in balance history
            $stmt = $con->prepare("INSERT INTO balance_history 
                                  (user_id, amount, type, reference_id, payment_method, notes)
                                  VALUES (?, ?, 'topup', ?, ?, ?)");
            $note = "Webhook: " . $eventType . " - " . substr($deliveredItem, 0, 50);
            $stmt->bind_param("idsss", 
                $transaction['user_id'],
                $transaction['amount'],
                $orderId,
                $transaction['payment_method'],
                $note
            );
            $stmt->execute();
        }

        $con->commit();
        logWebhook("Transaction updated successfully");
        
        http_response_code(200);
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $con->rollback();
        throw new Exception("Database update failed: " . $e->getMessage());
    }

} catch (Exception $e) {
    logWebhook("ERROR: " . $e->getMessage(), [
        'trace' => $e->getTraceAsString()
    ]);
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}