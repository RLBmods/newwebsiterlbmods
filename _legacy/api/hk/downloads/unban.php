<?php
require_once '../../../includes/session.php';
require_once '../../../db/connection.php';
requireAuth();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$downloadId = $input['download_id'] ?? null;

if (!$downloadId) {
    echo json_encode(['success' => false, 'message' => 'Missing download ID']);
    exit;
}

// Update download status
$stmt = $con->prepare("UPDATE download_history SET status = 'valid' WHERE id = ?");
$stmt->bind_param("i", $downloadId);
$success = $stmt->execute();

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to unban download']);
}