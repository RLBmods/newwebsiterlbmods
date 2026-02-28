<?php
//api/topup/transaction_by_order.php


header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../db/connection.php';
require_once '../../includes/session.php';

// Turn off error reporting for production or ensure errors don't output
error_reporting(0); // Disable error reporting
ini_set('display_errors', 0);

$response = ['success' => false, 'transaction' => null, 'error' => null];

try {
    // Validate session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access", 401);
    }

    if (!isset($_GET['order_id'])) {
        throw new Exception("Order ID is required", 400);
    }

    $userId = $_SESSION['user_id'];
    $orderId = $_GET['order_id'];

    // Get transaction by order ID
    $stmt = $con->prepare("
        SELECT * FROM payment_transactions 
        WHERE order_id = ? AND user_id = ?
    ");
    
    $stmt->bind_param("si", $orderId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $transaction = $result->fetch_assoc();

    if ($transaction) {
        // Safely decode API data if it exists and is not empty
        if (!empty($transaction['api_data']) && $transaction['api_data'] !== '0') {
            $apiData = json_decode($transaction['api_data'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $transaction['api_data'] = $apiData;
            } else {
                $transaction['api_data'] = null;
            }
        } else {
            $transaction['api_data'] = null;
        }
        
        $response = [
            'success' => true,
            'transaction' => $transaction
        ];
    } else {
        throw new Exception("Transaction not found", 404);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
}

// Ensure no output before this
if (ob_get_length()) ob_clean();
echo json_encode($response);
exit;
?>