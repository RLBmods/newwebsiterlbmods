<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

try {
    // Authentication
    if (!Auth::check()) {
        throw new Exception('Unauthorized', 401);
    }

    // Validate method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method Not Allowed', 405);
    }

    // Get and validate input
    $input = Validator::validateJsonInput();
    if (empty($input['stream_id'])) {
        throw new Exception('Stream ID is required', 400);
    }

    // End the stream
    $success = Stream::end($input['stream_id'], $_SESSION['user_id']);

    if (!$success) {
        throw new Exception('No active stream found to end', 404);
    }

    // Log activity
    ActivityLogger::log($_SESSION['user_id'], 'stream', 'Ended live stream');

    // Return response
    Response::success(['message' => 'Stream ended successfully']);

} catch (Exception $e) {
    Response::error($e->getMessage(), $e->getCode());
}