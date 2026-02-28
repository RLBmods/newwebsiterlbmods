<?php
require_once '../config.php';
require_once '../db/connection.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the return
$logFile = '/var/www/customer/rlbmods-design/logs/payment_return.log';
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Return hit: " . json_encode($_GET) . "\n", FILE_APPEND);

// Check if we have a transaction reference
if (isset($_GET['tran_ref'])) {
    $tranRef = $_GET['tran_ref'];
    
    // Verify the payment with PayTabs
    $ch = curl_init(PAYTABS_API_URL . '/payment/query');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'profile_id' => PAYTABS_PROFILE_ID,
            'tran_ref' => $tranRef
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . PAYTABS_SERVER_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 15
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] PayTabs verification: " . $result . "\n", FILE_APPEND);
    
    $response = json_decode($result, true);
    $success = false;
    
    if ($response && isset($response['payment_result'])) {
        if ($response['payment_result']['response_status'] === 'A') {
            // Payment was successful
            $success = true;
            
            // Update database
            try {
                $stmt = $con->prepare("UPDATE payment_transactions 
                                     SET status = 'completed', 
                                         transaction_id = ?,
                                         updated_at = NOW()
                                     WHERE transaction_id = ?");
                $stmt->bind_param("ss", $tranRef, $tranRef);
                $stmt->execute();
                
                // Get transaction details
                $stmt = $con->prepare("SELECT user_id, amount FROM payment_transactions WHERE transaction_id = ?");
                $stmt->bind_param("s", $tranRef);
                $stmt->execute();
                $transaction = $stmt->get_result()->fetch_assoc();
                
                if ($transaction) {
                    // Update user balance
                    $stmt = $con->prepare("UPDATE usertable SET balance = balance + ? WHERE id = ?");
                    $stmt->bind_param("di", $transaction['amount'], $transaction['user_id']);
                    $stmt->execute();
                    
                    // Record in balance history
                    $stmt = $con->prepare("INSERT INTO balance_history 
                                         (user_id, amount, type, reference_id, payment_method, notes)
                                         VALUES (?, ?, 'topup', ?, 'card', ?)");
                    $note = "Card payment completed (Ref: $tranRef)";
                    $stmt->bind_param("idss", 
                        $transaction['user_id'],
                        $transaction['amount'],
                        $tranRef,
                        $note
                    );
                    $stmt->execute();
                }
            } catch (Exception $e) {
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Database error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    }
    
    if ($success) {
        $_SESSION['payment_message'] = "Payment completed successfully! Your balance has been updated.";
    } else {
        $_SESSION['payment_message'] = "Payment verification failed. Please check your balance or contact support.";
    }
} else {
    $_SESSION['payment_message'] = "Invalid return URL parameters";
}

// Redirect back to the topup page
header('Location: /topup.php');
exit;