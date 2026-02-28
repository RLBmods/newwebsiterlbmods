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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid log ID']);
    exit();
}

$logId = (int)$_GET['id'];

$stmt = $con->prepare("SELECT * FROM logs WHERE id = ?");
$stmt->bind_param('i', $logId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Log not found']);
    exit();
}

$log = $result->fetch_assoc();

// Try to decode additional data if it's JSON
$additionalData = [];
if (!empty($log['additional_data'])) {
    $additionalData = json_decode($log['additional_data'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $additionalData = ['raw_data' => $log['additional_data']];
    }
}

echo json_encode([
    'success' => true,
    'log' => [
        'timestamp' => $log['timestamp'],
        'username' => $log['username'],
        'ip_address' => $log['ip_address'],
        'action_type' => $log['action_type'],
        'action' => $log['action'],
        'status' => $log['status'],
        'details' => $log['details'],
        'additional_data' => $additionalData
    ]
]);
?>