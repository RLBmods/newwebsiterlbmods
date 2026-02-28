<?php
require_once '../../../includes/session.php';
require_once '../../../db/connection.php';
requireStaff();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$downloadId = $input['download_id'] ?? null;
$reason = $input['reason'] ?? '';
$action = $input['action'] ?? 'none';

if (!$downloadId) {
    echo json_encode(['success' => false, 'message' => 'Invalid download ID']);
    exit;
}

try {
    $con->begin_transaction();
    
    // Update download status
    $stmt = $con->prepare("UPDATE download_keys SET status = 'banned' WHERE id = ?");
    $stmt->bind_param("i", $downloadId);
    $stmt->execute();
    
    // Additional actions
    if ($action === 'ban_user' || $action === 'ban_both') {
        // Get user ID from download
        $userStmt = $con->prepare("SELECT user_id FROM download_keys WHERE id = ?");
        $userStmt->bind_param("i", $downloadId);
        $userStmt->execute();
        $userId = $userStmt->get_result()->fetch_assoc()['user_id'];
        
        // Ban user (implement your actual ban logic here)
        // Example: $con->query("UPDATE usertable SET banned = 1 WHERE id = $userId");
    }
    
    if ($action === 'ban_ip' || $action === 'ban_both') {
        // Get IP from download
        $ipStmt = $con->prepare("SELECT ip_address FROM download_keys WHERE id = ?");
        $ipStmt->bind_param("i", $downloadId);
        $ipStmt->execute();
        $ipAddress = $ipStmt->get_result()->fetch_assoc()['ip_address'];
        
        // Ban IP (implement your actual IP ban logic here)
    }
    
    $con->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $con->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>