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
$duration = $input['duration'] ?? '1h';

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
    $expiresAt = null;
    
    // Calculate expiration date if not permanent
    if ($duration !== 'permanent') {
        $interval = substr($duration, -1);
        $amount = (int)substr($duration, 0, -1);
        
        switch ($interval) {
            case 'h': $expiresAt = date('Y-m-d H:i:s', strtotime("+$amount hours")); break;
            case 'd': $expiresAt = date('Y-m-d H:i:s', strtotime("+$amount days")); break;
            default: $expiresAt = date('Y-m-d H:i:s', strtotime("+1 hour")); break;
        }
    }
    
    // Add ban record
    $stmt = $con->prepare("INSERT INTO user_bans (user_id, moderator_id, reason, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $userId, $moderatorId, $reason, $expiresAt);
    $stmt->execute();
    
    // Update user banned status
    $stmt = $con->prepare("UPDATE usertable SET banned = 1 WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // Delete all user messages
    $stmt = $con->prepare("UPDATE messages SET deleted = 1 WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>