<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../db/connection.php';
require_once '../../includes/session.php';

$response = ['success' => false, 'transactions' => [], 'error' => null];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized", 401);
    }

    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = 10;
    $offset = ($page - 1) * $perPage;

    // Get total count
    $stmt = $con->prepare("SELECT COUNT(*) as total FROM payment_transactions WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];

    // Get transactions
    $stmt = $con->prepare("SELECT * FROM payment_transactions 
                          WHERE user_id = ? 
                          ORDER BY created_at DESC 
                          LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $_SESSION['user_id'], $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }

    $response['success'] = true;
    $response['transactions'] = $transactions;
    $response['total'] = $total;
    $response['pages'] = ceil($total / $perPage);
    $response['current_page'] = $page;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
}

echo json_encode($response);
exit;