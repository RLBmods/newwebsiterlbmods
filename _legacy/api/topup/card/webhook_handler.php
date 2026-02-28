<?php
require_once '../../../config.php';
require_once '../../../includes/session.php';
require_once '../../../db/connection.php';

// Database configuration
$dbConfig = [
    'host' => DB_HOST,
    'dbname' => DB_NAME,
    'username' => DB_USER,
    'password' => DB_PASS
];

// Get raw payload and headers
$payloadRaw = file_get_contents('php://input');
$headers = getallheaders();

try {
    // Validate webhook signature
    if (!isset($headers['Client-Key']) || $headers['Client-Key'] !== PAYTABS_CLIENT_KEY) {
        throw new Exception('Invalid client key');
    }

    // Parse payload
    $payload = json_decode($payloadRaw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON payload');
    }

    // Connect to database
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']}",
        $dbConfig['username'],
        $dbConfig['password']
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Process based on payment result
    if (isset($payload['payment_result'])) {
        $transactionId = $payload['cart_id'];
        $tranRef = $payload['tran_ref'];
        $amount = $payload['cart_amount'];
        $paymentResult = $payload['payment_result'];

        // Start transaction
        $db->beginTransaction();

        // Get transaction with lock
        $stmt = $db->prepare("
            SELECT id, status, user_id, amount 
            FROM payment_transactions 
            WHERE id = ? 
            FOR UPDATE
        ");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            throw new Exception("Transaction not found");
        }

        // Skip if already processed
        if ($transaction['status'] !== 'pending') {
            $db->commit();
            http_response_code(200);
            echo "Transaction already processed";
            exit;
        }

        // Process payment status
        switch ($paymentResult['response_status']) {
            case 'A': // Approved
                $status = 'completed';
                $stmt = $db->prepare("
                    UPDATE payment_transactions 
                    SET status = ?, transaction_id = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$status, $tranRef, $transactionId]);

                // Update user balance
                $stmt = $db->prepare("
                    UPDATE usertable 
                    SET balance = COALESCE(balance, 0) + ? 
                    WHERE id = ?
                ");
                $stmt->execute([$amount, $transaction['user_id']]);
                break;

            case 'E': // Error
            case 'D': // Declined
                $status = 'failed';
                $stmt = $db->prepare("
                    UPDATE payment_transactions 
                    SET status = ?, transaction_id = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$status, $tranRef, $transactionId]);
                break;

            case 'V': // Voided
                $status = 'voided';
                $stmt = $db->prepare("
                    UPDATE payment_transactions 
                    SET status = ?, transaction_id = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$status, $tranRef, $transactionId]);
                break;

            case 'P': // Pending
            default:
                $status = 'pending';
                $stmt = $db->prepare("
                    UPDATE payment_transactions 
                    SET transaction_id = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$tranRef, $transactionId]);
        }

        $db->commit();
        http_response_code(200);
        echo "Webhook processed successfully";

    } else {
        http_response_code(400);
        echo "Missing payment_result in payload";
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(400);
    echo "Webhook processing failed: " . $e->getMessage();
    
    // Log error
    error_log("Webhook Error: " . $e->getMessage() . "\nPayload: " . $payloadRaw);
}