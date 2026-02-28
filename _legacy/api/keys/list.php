<?php


require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

try {
    // Authentication
    if (!Auth::check()) {
        throw new Exception('Unauthorized', 401);
    }

    // Get keys with optional filtering
    $filters = [
        'status' => $_GET['status'] ?? null,
        'product' => $_GET['product'] ?? null
    ];

    $keys = LicenseKey::getAll($_SESSION['user_id'], $filters);

    // Return response
    Response::success(['keys' => $keys]);

} catch (Exception $e) {
    Response::error($e->getMessage(), $e->getCode());
}