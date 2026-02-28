<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../db/connection.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated', 401);
    }

    $userId = $_SESSION['user_id'];
    $query = $con->prepare("SELECT * FROM license_keys WHERE user_id = ? ORDER BY created_at DESC");
    $query->bind_param("i", $userId);
    $query->execute();
    $result = $query->get_result();

    $keys = [];
    while ($row = $result->fetch_assoc()) {
        $keys[] = [
            'id' => $row['id'],
            'key_value' => $row['key_value'],
            'product' => $row['product'],
            'purpose' => $row['purpose'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'expires_at' => $row['expires_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $keys
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}