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
    $required = ['title', 'description', 'platform', 'url'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field", 400);
        }
    }

    // Check if user already has an active stream
    $checkQuery = $con->prepare("SELECT id FROM streams WHERE user_id = ? AND status = 'live'");
    $checkQuery->bind_param("i", $_SESSION['user_id']);
    $checkQuery->execute();
    
    if ($checkQuery->get_result()->num_rows > 0) {
        throw new Exception('You already have an active stream', 400);
    }

    // Insert new stream
    $stmt = $con->prepare("INSERT INTO streams 
                          (user_id, title, description, platform, stream_url, status, started_at) 
                          VALUES (?, ?, ?, ?, ?, 'live', NOW())");
    $stmt->bind_param("issss", $_SESSION['user_id'], 
        sanitizeInput($input['title']),
        sanitizeInput($input['description']),
        sanitizeInput($input['platform']),
        sanitizeInput($input['url'])
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to start stream: " . $con->error, 500);
    }

    // Log activity
    logActivity($_SESSION['user_id'], 'stream', "Started live stream: " . $input['title']);

    // Return success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Stream started successfully',
        'data' => [
            'stream_id' => $stmt->insert_id,
            'title' => $input['title'],
            'status' => 'live'
        ]
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}