<?php
// Start output buffering at the very beginning
ob_start();

header('Content-Type: application/json');
require_once '../../../includes/session.php';
require_once '../../../includes/functions.php';
require_once '../../../db/connection.php';
require_once '../../../includes/logging.php';

// Authentication
requireAuth();
requireStaff();

$response = ['success' => false, 'message' => ''];

try {
    // Get the raw POST data
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('No input data received');
    }

    // Decode the JSON data
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data received');
    }

    if (!isset($data['id']) || empty($data['id'])) {
        throw new Exception('Ban ID is required');
    }

    $banId = (int)$data['id'];
    if ($banId <= 0) {
        throw new Exception('Invalid ban ID');
    }

    // Check if ban exists and is active
    $stmt = $pdo->prepare("SELECT id FROM bans WHERE id = ? AND is_active = 1");
    $stmt->execute([$banId]);
    if (!$stmt->fetch()) {
        throw new Exception('Active ban not found');
    }

    // Deactivate the ban
    $stmt = $pdo->prepare("UPDATE bans SET 
                          is_active = 0,
                          unbanned_at = NOW(),
                          unbanned_by = ?,
                          unbanned_by_username = ?
                          WHERE id = ?");
    $stmt->execute([
        $_SESSION['user_id'],
        $_SESSION['user_name'],
        $banId
    ]);

    $response = [
        'success' => true,
        'message' => 'User unbanned successfully'
    ];
    
    logAction($_SESSION['user_id'], 'unban', "Unbanned ban ID: $banId");

} catch (Exception $e) {
    http_response_code(400);
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

// Clean all output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Ensure only JSON is output
echo json_encode($response);
exit;