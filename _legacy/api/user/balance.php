<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db/connection.php';
require_once __DIR__ . '/../../includes/session.php';

$response = ['success' => false, 'balance' => 0];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized", 401);
    }

    $stmt = $con->prepare("SELECT balance FROM usertable WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        $response['success'] = true;
        $response['balance'] = $result['balance'];
    } else {
        throw new Exception("User not found", 404);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit;