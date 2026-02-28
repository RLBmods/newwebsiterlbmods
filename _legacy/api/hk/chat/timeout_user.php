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
$duration = $input['duration'] ?? 5; // in minutes

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
    $expiresAt = date('Y-m-d H:i:s', strtotime("+$duration minutes"));
    
    // Mute user until timeout expires
    $stmt = $con->prepare("UPDATE usertable SET muted = 1, muted_until = ? WHERE id = ?");
    $stmt->bind_param("si", $expiresAt, $userId);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>