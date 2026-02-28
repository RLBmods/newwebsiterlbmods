<?php
require_once '../../../includes/session.php';
require_once '../../../db/connection.php';

header('Content-Type: application/json');

// Verify authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Get order ID from request
$orderId = $_GET['order_id'] ?? '';

if (empty($orderId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Order ID required']);
    exit;
}

try {
    // Get transaction status - updated to work with your transaction_history table
    $stmt = $con->prepare("
        SELECT th.*, u.balance as current_balance 
        FROM transaction_history th 
        JOIN usertable u ON th.user_id = u.id 
        WHERE th.order_id = ? AND th.user_id = ?
    ");
    $stmt->bind_param("si", $orderId, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $transaction = $result->fetch_assoc();
    
    if (!$transaction) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        exit;
    }
    
    // For all payment methods, check with payment gateway if status is pending
    if ($transaction['status'] === 'pending') {
        $status = checkPaymentStatus($orderId, $transaction);
        
        if ($status !== $transaction['status']) {
            // Update transaction status
            $updateStmt = $con->prepare("UPDATE transaction_history SET status = ? WHERE order_id = ?");
            $updateStmt->bind_param("ss", $status, $orderId);
            $updateStmt->execute();
            
            // If completed, update user balance
            if ($status === 'completed') {
                $balanceStmt = $con->prepare("UPDATE usertable SET balance = balance + ? WHERE id = ?");
                $balanceStmt->bind_param("di", $transaction['amount'], $_SESSION['user_id']);
                $balanceStmt->execute();
                
                // Get updated balance
                $balanceQuery = $con->prepare("SELECT balance FROM usertable WHERE id = ?");
                $balanceQuery->bind_param("i", $_SESSION['user_id']);
                $balanceQuery->execute();
                $balanceResult = $balanceQuery->get_result();
                $userData = $balanceResult->fetch_assoc();
                $transaction['current_balance'] = $userData['balance'];
            }
            
            $transaction['status'] = $status;
        }
    }
    
    echo json_encode([
        'success' => true,
        'status' => $transaction['status'],
        'current_balance' => $transaction['current_balance'] ?? $userData['balance'] ?? 0,
        'transaction' => $transaction
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function checkPaymentStatus($orderId, $transaction) {
    // For card payments, check with PayTabs
    if (strpos($orderId, 'TST') === 0 || strpos($orderId, 'TXN') === 0 || strpos($orderId, 'PTB') === 0) {
        return checkPayTabsStatus($orderId);
    }
    
    // For crypto payments, check with your crypto payment processor
    // This would typically involve checking blockchain confirmations
    // For now, return the existing status
    return $transaction['status'];
}

function checkPayTabsStatus($orderId) {
    // Implement PayTabs status check
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => PAYTABS_API_URL . '/payment/query',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'profile_id' => PAYTABS_PROFILE_ID,
            'tran_ref' => $orderId
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . PAYTABS_SERVER_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return 'pending';
    }
    
    $responseData = json_decode($result, true);
    if (!$responseData || !isset($responseData['payment_result'])) {
        return 'pending';
    }
    
    // Map PayTabs status to your status
    $paymentResult = $responseData['payment_result'];
    switch ($paymentResult['response_status']) {
        case 'A': // Approved
            return 'completed';
        case 'E': // Error
            return 'failed';
        case 'V': // Void
            return 'cancelled';
        case 'H': // Hold
            return 'pending';
        case 'P': // Pending
            return 'pending';
        default:
            return 'pending';
    }
}
?>