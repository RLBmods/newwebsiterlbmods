<?php
require_once '../../../includes/session.php';
require_once '../../../db/connection.php';
requireStaff();

header('Content-Type: application/json');

$downloadId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$downloadId) {
    echo json_encode(['success' => false, 'message' => 'Invalid download ID']);
    exit;
}

try {
    // Get download details
    $stmt = $con->prepare("
        SELECT dk.*, p.name as product_name, p.version as product_version, u.name
        FROM download_keys dk
        JOIN products p ON dk.product_id = p.id
        JOIN usertable u ON dk.user_id = u.id
        WHERE dk.id = ?
    ");
    $stmt->bind_param("i", $downloadId);
    $stmt->execute();
    $download = $stmt->get_result()->fetch_assoc();
    
    if (!$download) {
        echo json_encode(['success' => false, 'message' => 'Download not found']);
        exit;
    }
    
    // Get error count
    $errorStmt = $con->prepare("SELECT COUNT(*) as error_count FROM download_errors WHERE key_id = ?");
    $errorStmt->bind_param("i", $downloadId);
    $errorStmt->execute();
    $errorCount = $errorStmt->get_result()->fetch_assoc()['error_count'];
    
    $download['error_count'] = $errorCount;
    
    echo json_encode(['success' => true, 'download' => $download]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>