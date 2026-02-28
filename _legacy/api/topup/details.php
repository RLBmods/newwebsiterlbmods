<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../db/connection.php';
require_once '../../includes/session.php';

$response = ['success' => false];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access", 401);
    }
    
    $userId = $_SESSION['user_id'];
    $orderId = $_GET['order_id'] ?? '';
    
    if (empty($orderId)) {
        throw new Exception("Order ID is required", 400);
    }
    
    // Check crypto transactions first
    $stmt = $con->prepare("SELECT * FROM payment_transactions WHERE user_id = ? AND order_id = ?");
    $stmt->bind_param("is", $userId, $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    
    // If not found in crypto transactions, check card transactions
    if (!$payment) {
        $stmt = $con->prepare("
            SELECT 
                id, 
                order_id, 
                amount, 
                'card' as payment_method, 
                status, 
                created_at
            FROM transaction_history 
            WHERE user_id = ? AND order_id = ?
        ");
        $stmt->bind_param("is", $userId, $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();
    }
    
    if (!$payment) {
        throw new Exception("Payment not found", 404);
    }
    
    $payment['amount'] = (float)$payment['amount'];
    
    $response = [
        'success' => true,
        'payment' => $payment
    ];
    
} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit;