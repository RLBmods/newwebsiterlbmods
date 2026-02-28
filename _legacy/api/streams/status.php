<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

try {
    // Authentication
    if (!Auth::check()) {
        throw new Exception('Unauthorized', 401);
    }

    // Get current stream
    $stream = Stream::getCurrent($_SESSION['user_id']);

    if (!$stream) {
        Response::success(['data' => null]);
        exit;
    }

    // Calculate duration
    $started = new DateTime($stream['started_at']);
    $now = new DateTime();
    $interval = $now->diff($started);
    $duration = $interval->format('%hh %im');

    // Return response
    Response::success([
        'data' => [
            'id' => $stream['id'],
            'title' => $stream['title'],
            'platform' => $stream['platform'],
            'url' => $stream['stream_url'],
            'status' => $stream['status'],
            'started_at' => $stream['started_at'],
            'duration' => $duration
        ]
    ]);

} catch (Exception $e) {
    Response::error($e->getMessage(), $e->getCode());
}