<?php
require_once '../../db/connection.php';
require_once '../../includes/session.php';

requireAuth();

header('Content-Type: application/json');

try {
    $userId = $_SESSION['user_id'];
    
    $stmt = $con->prepare("SELECT balance FROM usertable WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('User not found');
    }
    
    $user = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'balance' => $user['balance']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}