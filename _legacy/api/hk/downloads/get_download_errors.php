<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../../includes/session.php';
require_once '../../../includes/functions.php';
require_once '../../../db/connection.php';

// Authentication
requireAuth();
requireStaff();

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Download ID required']);
    exit();
}

$downloadId = (int)$_GET['id'];

// Get errors for this download
$stmt = $con->prepare("SELECT * FROM download_errors WHERE key_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $downloadId);
$stmt->execute();
$errors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'errors' => $errors
]);
?>