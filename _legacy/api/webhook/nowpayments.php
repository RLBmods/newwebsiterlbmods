<?php
// api/topup/webhook/nowpayments.php

header('Content-Type: application/json');

$logDir = __DIR__ . '/../../logs/';
$logFile = $logDir . 'nowpayments_webhook.log';

if (!file_exists($logDir)) {
    @mkdir($logDir, 0755, true);
}

function logWebhook($message, $data = null) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    if ($data !== null) {
        $logEntry .= "\n" . json_encode($data, JSON_PRETTY_PRINT);
    }
    $logEntry .= "\n" . str_repeat("-", 50) . "\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

logWebhook("=== WEBHOOK ATTEMPT START ===");

try {
    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput, true);
    
    if (!$payload) throw new Exception('Empty Payload');

    $paymentId = $payload['payment_id'];
    $orderId = $payload['order_id'];
    $paymentStatus = $payload['payment_status'];
    $actuallyPaidCrypto = floatval($payload['actually_paid'] ?? 0);

    require_once __DIR__ . '/../../../config.php';
    require_once __DIR__ . '/../../../db/connection.php';

    // Fetch the transaction
    $stmt = $con->prepare("SELECT t.id, t.user_id, t.amount, t.status FROM payment_transactions t WHERE t.order_id = ? AND t.transaction_id = ?");
    $stmt->bind_param("ss", $orderId, $paymentId);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();

    if (!$transaction) {
        throw new Exception("Transaction $orderId not found in database.");
    }

    // DEBUG LOG: This will show us EXACTLY what the DB is giving us for 'amount'
    logWebhook("DATABASE CHECK", [
        'db_amount_column' => $transaction['amount'],
        'crypto_received_from_payload' => $actuallyPaidCrypto,
        'payment_status' => $paymentStatus
    ]);

    $successStatuses = ['confirmed', 'finished', 'sending'];
    
    if (in_array($paymentStatus, $successStatuses) && $transaction['status'] !== 'completed') {
        
        // WE FORCE THE USD AMOUNT FROM OUR 'amount' COLUMN
        $usdAmount = floatval($transaction['amount']); 

        if ($usdAmount <= 0) {
            throw new Exception("USD Amount in database is 0 or invalid.");
        }

        $con->begin_transaction();

        try {
            // 1. Update Transaction
            $update = $con->prepare("UPDATE payment_transactions SET status = 'completed', amount_received = ?, updated_at = NOW() WHERE id = ?");
            $update->bind_param("di", $actuallyPaidCrypto, $transaction['id']);
            $update->execute();

            // 2. Update User Balance (Using the USD amount)
            $balance = $con->prepare("UPDATE usertable SET balance = balance + ? WHERE id = ?");
            $balance->bind_param("di", $usdAmount, $transaction['user_id']);
            $balance->execute();

            $con->commit();
            logWebhook("BALANCE UPDATED SUCCESSFULLY", [
                'user_id' => $transaction['user_id'],
                'added_usd' => $usdAmount,
                'received_crypto' => $actuallyPaidCrypto
            ]);

        } catch (Exception $dbEx) {
            $con->rollback();
            throw $dbEx;
        }
    } else {
        logWebhook("No balance action taken", ['status' => $paymentStatus, 'current_db_status' => $transaction['status']]);
    }

    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    logWebhook("WEBHOOK ERROR", ['msg' => $e->getMessage()]);
    http_response_code(400);
}