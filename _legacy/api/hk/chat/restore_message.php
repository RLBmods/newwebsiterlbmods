<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../../db/connection.php';
require_once '../../../vendor/autoload.php';
require_once '../../../includes/get_user_info.php';
require_once '../../../includes/session.php';
require_once '../../../includes/logging.php';

requireAuth();
requireStaff();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'Message ID required']);
    exit;
}

try {
    $stmt = $con->prepare("UPDATE messages SET deleted = 0 WHERE id = ?");
    $stmt->bind_param("i", $input['id']);
    $stmt->execute();
    
    // Get the restored message
    $stmt = $con->prepare("SELECT m.*, u.name as username 
                          FROM messages m
                          LEFT JOIN usertable u ON m.username = u.name
                          WHERE m.id = ?");
    $stmt->bind_param("i", $input['id']);
    $stmt->execute();
    $message = $stmt->get_result()->fetch_assoc();
    
    echo json_encode(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>