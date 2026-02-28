<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';

// Verify webhook request (you should implement proper verification)
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (empty($data['event']) || empty($data['data']['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid webhook data']);
    exit;
}

try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Find the transaction
    $stmt = $pdo->prepare("SELECT * FROM payment_transactions WHERE order_id = ?");
    $stmt->execute([$data['data']['id']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        http_response_code(404);
        echo json_encode(['error' => 'Transaction not found']);
        exit;
    }
    
    // Determine status from webhook event
    $event = strtolower($data['event']);
    $statusMap = [
        'order.completed' => 'paid',
        'order.paid' => 'paid',
        'order.failed' => 'failed',
        'order.expired' => 'expired'
    ];
    
    $status = $statusMap[$event] ?? $transaction['status'];
    
    // Update transaction status if changed
    if ($status !== $transaction['status']) {
        $stmt = $pdo->prepare("UPDATE payment_transactions SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $transaction['id']]);
        
        // Record status update
        $stmt = $pdo->prepare("INSERT INTO payment_status_updates 
            (transaction_id, status, update_data) 
            VALUES (?, ?, ?)");
        
        $stmt->execute([
            $transaction['id'],
            $status,
            $payload
        ]);
        
        // If payment is successful, update user balance
        if ($status === 'paid') {
            $stmt = $pdo->prepare("UPDATE usertable SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$transaction['amount'], $transaction['user_id']]);
        }
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}