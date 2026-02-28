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

    // Get recent activities
    $limit = min((int)($_GET['limit'] ?? 5), 20); // Max 20 activities
    $activities = ActivityLogger::getRecent($_SESSION['user_id'], $limit);

    // Return response
    Response::success(['activities' => $activities]);

} catch (Exception $e) {
    Response::error($e->getMessage(), $e->getCode());
}