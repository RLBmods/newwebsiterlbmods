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

$flag = isset($input['flag']) ? (bool)$input['flag'] : false;

try {
    $stmt = $con->prepare("UPDATE messages SET flagged = ? WHERE id = ?");
    $stmt->bind_param("ii", $flag, $input['id']);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'flagged' => $flag]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>