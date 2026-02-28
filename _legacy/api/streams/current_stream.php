<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../db/connection.php';

header('Content-Type: application/json');

try {
    // Verify authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated', 401);
    }

    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input', 400);
    }

    // Validate required fields
    if (empty($input['stream_id'])) {
        throw new Exception("Missing required field: stream_id", 400);
    }

    $streamId = (int)$input['stream_id'];
    $userId = $_SESSION['user_id'];

    // Update stream status
    $stmt = $con->prepare("UPDATE streams 
                          SET status = 'ended', ended_at = NOW() 
                          WHERE id = ? AND user_id = ? AND status = 'live'");
    $stmt->bind_param("ii", $streamId, $userId);

    if (!$stmt->execute()) {
        throw new Exception("Failed to end stream: " . $con->error, 500);
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception("No active stream found to end", 404);
    }

    // Log activity
    logActivity($userId, 'stream', "Ended live stream");

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Stream ended successfully'
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}