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

    $transactionId = $_GET['id'] ?? null;
    if (empty($transactionId)) {
        throw new Exception("Transaction ID is required", 400);
    }

    // Get transaction details
    $stmt = $con->prepare("SELECT * FROM payment_transactions 
                          WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $transactionId, $_SESSION['user_id']);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();

    if (!$transaction) {
        throw new Exception("Transaction not found", 404);
    }

    $response = [
        'success' => true,
        'transaction' => $transaction
    ];

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit;