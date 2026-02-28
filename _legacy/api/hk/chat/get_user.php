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

if (empty($_GET['username'])) {
    echo json_encode(['success' => false, 'message' => 'Username required']);
    exit;
}

$username = $_GET['username'];

try {
    // Get user details
    $stmt = $con->prepare("SELECT *, 
                          (SELECT COUNT(*) FROM messages WHERE username = u.name) as message_count,
                          (SELECT COUNT(*) FROM user_warnings WHERE user_id = u.id) as warnings_count,
                          (SELECT COUNT(*) FROM user_bans WHERE user_id = u.id) as bans_count
                          FROM usertable u WHERE name = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    echo json_encode(['success' => true, 'user' => $user]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>