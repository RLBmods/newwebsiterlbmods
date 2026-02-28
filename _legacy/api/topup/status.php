<?php
// 1. Initialize with strict output control
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Expires: 0');
header('Pragma: no-cache');

// 2. Error handling configuration
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../../logs/status_errors.log');

// 3. Authentication and initialization
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db/connection.php';
require_once __DIR__ . '/../../includes/session.php';

$response = ['success' => false, 'error' => null];

try {
    // 4. Verify session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized", 401);
    }

    // 5. Input validation
    $orderId = $_GET['order_id'] ?? '';
    if (empty($orderId) || !preg_match('/^NP_[a-z0-9]+_\d+$/i', $orderId)) {
        throw new Exception("Invalid Order ID", 400);
    }

    // 6. Database connection
    $stmt = $con->prepare("SELECT 
            t.*, 
            u.email,
            u.balance,
            u.current_ip
        FROM payment_transactions t
        JOIN usertable u ON t.user_id = u.id
        WHERE t.order_id = ? AND t.user_id = ?");
    $stmt->bind_param("si", $orderId, $_SESSION['user_id']);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();
    
    if (!$transaction) {
        throw new Exception("Transaction not found", 404);
    }

    // 7. Prepare base response
    $response = [
        'success' => true,
        'status' => $transaction['status'],
        'payment_method' => $transaction['payment_method'],
        'amount' => (float)$transaction['amount'],
        'created_at' => $transaction['created_at'],
        'current_balance' => (float)$transaction['balance'],
        'network_fee' => $transaction['network_fee'] ? (float)$transaction['network_fee'] : null,
        'gateway_fee' => $transaction['gateway_fee'] ? (float)$transaction['gateway_fee'] : null,
        'amount_received' => $transaction['amount_received'] ? (float)$transaction['amount_received'] : null,
        'confirmations' => $transaction['confirmations'] ? (int)$transaction['confirmations'] : null,
        'delivered_item' => $transaction['delivered_item'] ?? null
    ];

    // 8. Check with NowPayments API if pending
    if ($transaction['status'] === 'pending') {
        $paymentId = $transaction['transaction_id'];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.nowpayments.io/v1/payment/$paymentId",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . NOWPAYMENTS_API_KEY,
                'Accept: application/json',
                'User-Agent: RLBmods-Cron/1.0'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ]);
        
        $apiResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200) {
            $apiData = json_decode($apiResponse, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON response from NowPayments API");
            }
            
            // 9. Map NowPayments status to our system
            $apiStatus = $apiData['payment_status'] ?? 'waiting';
            $newStatus = $transaction['status']; // Default to current status
            
            $statusMapping = [
                'finished' => 'completed',
                'confirmed' => 'completed',
                'sending' => 'completed',
                'partially_paid' => 'pending',
                'waiting' => 'pending',
                'expired' => 'failed',
                'failed' => 'failed',
                'refunded' => 'refunded'
            ];
            
            $newStatus = $statusMapping[$apiStatus] ?? $transaction['status'];
            
            // 10. Update transaction if status changed
            if ($newStatus !== $transaction['status']) {
                $con->begin_transaction();
                
                try {
                    // Update main transaction
                    $stmt = $con->prepare("UPDATE payment_transactions 
                        SET status = ?, 
                            updated_at = NOW(),
                            amount_received = ?,
                            network_fee = ?,
                            confirmations = ?
                        WHERE id = ?");
                    
                    $amountReceived = $apiData['actually_paid'] ?? $transaction['amount_received'];
                    $networkFee = $apiData['outgoing_network_fee'] ?? $transaction['network_fee'];
                    $confirmations = $apiData['confirmations'] ?? $transaction['confirmations'];
                    
                    $stmt->bind_param(
                        "sddii",
                        $newStatus,
                        $amountReceived,
                        $networkFee,
                        $confirmations,
                        $transaction['id']
                    );
                    $stmt->execute();
                    
                    // Update user balance if completed
                    if ($newStatus === 'completed') {
                        $stmt = $con->prepare("UPDATE usertable 
                            SET balance = balance + ? 
                            WHERE id = ?");
                        $stmt->bind_param("di", $transaction['amount'], $transaction['user_id']);
                        $stmt->execute();
                        
                        // Update response with new balance
                        $stmt = $con->prepare("SELECT balance FROM usertable WHERE id = ?");
                        $stmt->bind_param("i", $transaction['user_id']);
                        $stmt->execute();
                        $balanceResult = $stmt->get_result()->fetch_assoc();
                        $response['current_balance'] = (float)$balanceResult['balance'];
                    }
                    
                    $con->commit();
                    $response['status'] = $newStatus;
                    
                } catch (Exception $e) {
                    $con->rollback();
                    // Log error but don't fail the request
                    error_log("Status update failed: " . $e->getMessage());
                }
            }
            
            // 11. Update response with latest API data
            $response['network_fee'] = isset($apiData['outgoing_network_fee']) ? (float)$apiData['outgoing_network_fee'] : null;
            $response['amount_received'] = isset($apiData['actually_paid']) ? (float)$apiData['actually_paid'] : null;
            $response['confirmations'] = isset($apiData['confirmations']) ? (int)$apiData['confirmations'] : null;
        }
    }
    
    // 12. Add human-readable status
    $statusMessages = [
        'pending' => 'Waiting for payment',
        'completed' => 'Payment completed',
        'failed' => 'Payment failed',
        'expired' => 'Payment expired',
        'refunded' => 'Payment refunded'
    ];
    $response['status_message'] = $statusMessages[$response['status']] ?? 'Unknown status';

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
    
    if ($e->getCode() === 401) {
        // Clear session if unauthorized
        session_unset();
        session_destroy();
    }
}

echo json_encode($response);
exit;