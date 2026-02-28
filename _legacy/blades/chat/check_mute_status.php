<?php
require_once '../../includes/session.php';
require_once '../../db/connection.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }

    $user_id = $_SESSION['user_id'];
    
    // Check if user is muted using usertable.muted column
    $stmt = $con->prepare("SELECT muted FROM usertable WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user && $user['muted'] == 1) {
        echo json_encode([
            'muted' => true,
            'reason' => 'No reason specified', // You can modify this if you store reasons elsewhere
            'is_permanent' => true // Assuming permanent if using simple muted column
        ]);
    } else {
        echo json_encode(['muted' => false]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}