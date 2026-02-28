<?php
//api/topup/get_transaction_details.php

header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../db/connection.php';
require_once '../../includes/session.php';

$response = ['success' => false, 'transaction' => null];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized", 401);
    }

    $transactionId = $_GET['id'] ?? $_GET['order_id'] ?? null;
    $gateway = $_GET['gateway'] ?? null;

    if (!$transactionId || !$gateway) {
        throw new Exception("Transaction ID and gateway are required", 400);
    }

    if ($gateway === 'sellsn') {
        $stmt = $con->prepare("
            SELECT 
                id,
                order_id,
                amount,
                'sellsn' as gateway,
                payment_method,
                status,
                created_at,
                checkout_url,
                crypto_address,
                sell_key,
                delivered_item,
                delivery_status,
                note
            FROM payment_transactions 
            WHERE (id = ? OR order_id = ?) AND user_id = ?
        ");
        $stmt->bind_param("ssi", $transactionId, $transactionId, $_SESSION['user_id']);
    } else {
        $stmt = $con->prepare("
            SELECT 
                id,
                order_id,
                amount,
                'paytop' as gateway,
                'card' as payment_method,
                status,
                created_at,
                NULL as checkout_url,
                NULL as crypto_address,
                NULL as sell_key,
                NULL as delivered_item,
                NULL as delivery_status,
                NULL as note
            FROM transaction_history 
            WHERE (id = ? OR order_id = ?) AND user_id = ?
        ");
        $stmt->bind_param("ssi", $transactionId, $transactionId, $_SESSION['user_id']);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $transaction = $result->fetch_assoc();

    if (!$transaction) {
        throw new Exception("Transaction not found", 404);
    }

    $response['success'] = true;
    $response['transaction'] = $transaction;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
}

echo json_encode($response);
exit;