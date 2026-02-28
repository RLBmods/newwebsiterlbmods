<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../db/connection.php';
require_once '../../includes/session.php';

$response = ['success' => false, 'error' => null];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized", 401);
    }

    $orderId = $_GET['order_id'] ?? null;
    if (!$orderId) {
        throw new Exception("Order ID is required", 400);
    }

    $stmt = $con->prepare("SELECT * FROM payment_transactions WHERE order_id = ? AND user_id = ?");
    $stmt->bind_param("si", $orderId, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        throw new Exception("Transaction not found", 404);
    }

    $response = [
        'success' => true,
        'order_id' => $result['order_id'],
        'amount' => $result['amount'],
        'payment_method' => $result['payment_method'],
        'status' => $result['status'],
        'payment_address' => $result['crypto_address'],
        'payment_amount' => $result['amount'],
        'payment_currency' => 'USD',
        'paypal_email' => 'mreagle13337@gmail.com',
        'paypal_note' => $result['sell_key'] ?? '',
        'checkout_url' => $result['checkout_url'],
        'created_at' => $result['created_at'],
        'updated_at' => $result['updated_at']
    ];

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
}

echo json_encode($response);
exit;