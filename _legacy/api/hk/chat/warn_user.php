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

if (empty($input['username'])) {
    echo json_encode(['success' => false, 'message' => 'Username required']);
    exit;
}

$username = $input['username'];
$reason = $input['reason'] ?? 'Violation of chat rules';

try {
    // Get user ID
    $stmt = $con->prepare("SELECT id FROM usertable WHERE name = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $userId = $user['id'];
    $moderatorId = $_SESSION['user_id'];
    
    // Add warning
    $stmt = $con->prepare("INSERT INTO user_warnings (user_id, moderator_id, reason) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $userId, $moderatorId, $reason);
    $stmt->execute();
    
    // Update user warning count
    $stmt = $con->prepare("UPDATE usertable SET warnings_count = warnings_count + 1 WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>