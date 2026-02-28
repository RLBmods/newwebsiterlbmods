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

if (!isset($input['duration'])) {
    echo json_encode(['success' => false, 'message' => 'Duration required']);
    exit;
}

try {
    // In a real implementation, you would store this in a settings table
    // For this example, we'll just return success
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>