<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    $required = ['title', 'platform', 'url'];
    Validator::validateRequiredFields($input, $required);

    // Check for existing stream
    if (Stream::hasActiveStream($_SESSION['user_id'])) {
        throw new Exception('You already have an active stream', 400);
    }

    // Create new stream
    $streamId = Stream::create([
        'user_id' => $_SESSION['user_id'],
        'title' => $input['title'],
        'description' => $input['description'] ?? '',
        'platform' => $input['platform'],
        'stream_url' => $input['url'],
        'status' => 'live'
    ]);

    // Log activity
    ActivityLogger::log($_SESSION['user_id'], 'stream', "Started stream: {$input['title']}");

    // Return response
    Response::success([
        'message' => 'Stream started successfully',
        'stream_id' => $streamId,
        'status' => 'live'
    ], 201);

} catch (Exception $e) {
    Response::error($e->getMessage(), $e->getCode());
}