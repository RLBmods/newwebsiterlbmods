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

    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);
    $orderId = $data['order_id'] ?? null;

    if (!$orderId) {
        throw new Exception("Order ID is required", 400);
    }

    // Verify the transaction belongs to this user
    $stmt = $con->prepare("SELECT * FROM payment_transactions WHERE order_id = ? AND user_id = ?");
    $stmt->bind_param("si", $orderId, $_SESSION['user_id']);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();

    if (!$transaction) {
        throw new Exception("Transaction not found", 404);
    }

    // Update status to cancelled
    $stmt = $con->prepare("UPDATE payment_transactions SET status = 'cancelled', updated_at = NOW() WHERE order_id = ?");
    $stmt->bind_param("s", $orderId);
    $stmt->execute();

    $response['success'] = true;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
}

echo json_encode($response);
exit;