<?php
// In your API files (get_pending_payments.php, history.php), add these headers:
header('HTTP/1.1 200 OK'); // Force HTTP/1.1
header('Connection: close');
header('Cache-Control: no-store, no-cache, must-revalidate');

header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db/connection.php';
require_once __DIR__ . '/../../includes/session.php';

$response = ['success' => false, 'pending_payments' => []];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized", 401);
    }

    // Get pending payments from both gateways
    $stmt = $con->prepare("
        (SELECT 
            id,
            order_id,
            amount,
            'sellsn' as gateway,
            payment_method,
            status,
            created_at,
            checkout_url,
            crypto_address,
            sell_key
        FROM payment_transactions 
        WHERE user_id = ? AND status = 'pending')
        
        UNION
        
        (SELECT 
            id,
            order_id,
            amount,
            'paytop' as gateway,
            'card' as payment_method,
            status,
            created_at,
            NULL as checkout_url,
            NULL as crypto_address,
            NULL as sell_key
        FROM transaction_history 
        WHERE user_id = ? AND status = 'pending')
        
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    $pendingPayments = [];
    while ($row = $result->fetch_assoc()) {
        $pendingPayments[] = $row;
    }

    $response['success'] = true;
    $response['pending_payments'] = $pendingPayments;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
}

echo json_encode($response);
exit;