<?php
// Configure logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/your/webhook.log');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db/connection.php';

// Verify webhook signature if available
if (isset($_SERVER['HTTP_X_SELLSN_SIGNATURE'])) {
    $receivedSig = $_SERVER['HTTP_X_SELLSN_SIGNATURE'];
    $payload = file_get_contents('php://input');
    $expectedSig = hash_hmac('sha256', $payload, SELLSN_WEBHOOK_SECRET);
    
    if (!hash_equals($expectedSig, $receivedSig)) {
        http_response_code(401);
        exit('Invalid signature');
    }
}

// Process the webhook
try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON payload');
    }
    
    // Essential data validation
    if (empty($input['data']['order']['id']) {
        throw new Exception('Missing order ID');
    }
    
    $orderId = $input['data']['order']['id'];
    $status = strtolower($input['data']['order']['status']);
    
    // Map Sellsn status to our system
    $statusMap = [
        'delivered' => 'completed',
        'paid' => 'completed',
        'completed' => 'completed',
        'failed' => 'failed',
        'expired' => 'failed',
        'cancelled' => 'failed',
        'refunded' => 'refunded'
    ];
    
    $newStatus = $statusMap[$status] ?? 'pending';
    
    // Begin transaction
    $con->begin_transaction();
    
    try {
        // 1. Get the transaction from our database
        $stmt = $con->prepare("SELECT * FROM payment_transactions WHERE order_id = ? FOR UPDATE");
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $transaction = $stmt->get_result()->fetch_assoc();
        
        if (!$transaction) {
            throw new Exception("Transaction not found");
        }
        
        // Skip if already in final state
        if (in_array($transaction['status'], ['completed', 'failed', 'refunded'])) {
            $con->commit();
            http_response_code(200);
            exit('Already processed');
        }
        
        // 2. Update the transaction
        $updateStmt = $con->prepare("UPDATE payment_transactions 
            SET status = ?,
                updated_at = NOW(),
                transaction_id = ?,
                crypto_address = ?,
                confirmations = ?,
                amount_received = ?,
                network_fee = ?,
                gateway_fee = ?,
                delivered_item = ?
            WHERE id = ?");
        
        $updateStmt->bind_param(
            "ssssdddsi",
            $newStatus,
            $input['data']['order']['transactionId'] ?? $transaction['transaction_id'],
            $input['data']['order']['cryptocurrencyAddress'] ?? $transaction['crypto_address'],
            $input['data']['order']['confirmations'] ?? $transaction['confirmations'],
            $input['data']['order']['amountPaid'] ?? $transaction['amount'],
            $input['data']['order']['networkFee'] ?? 0,
            $input['data']['order']['gatewayFee'] ?? 0,
            $input['data']['order']['deliveredItem'] ?? null,
            $transaction['id']
        );
        $updateStmt->execute();
        
        // 3. Update user balance if completed
        if ($newStatus === 'completed') {
            $balanceStmt = $con->prepare("UPDATE usertable 
                SET balance = balance + ? 
                WHERE id = ?");
            $balanceStmt->bind_param("di", $transaction['amount'], $transaction['user_id']);
            $balanceStmt->execute();
        }
        
        $con->commit();
        http_response_code(200);
        echo 'OK';
        
    } catch (Exception $e) {
        $con->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}