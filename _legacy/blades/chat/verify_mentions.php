<?php
declare(strict_types=1);
ini_set('display_errors', 0); // Disable error display in production
header('Content-Type: application/json');

require_once '../../includes/session.php';
require_once '../../db/connection.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data', 400);
    }

    $mentions = $input['mentions'] ?? [];
    $invalid = [];

    if (!empty($mentions)) {
        $placeholders = implode(',', array_fill(0, count($mentions), '?'));
        $types = str_repeat('s', count($mentions));
        
        $stmt = $con->prepare("SELECT name FROM usertable WHERE name IN ($placeholders)");
        $stmt->bind_param($types, ...$mentions);
        $stmt->execute();
        $validUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $validUsers = array_column($validUsers, 'name');
        $invalid = array_diff($mentions, $validUsers);
    }

    echo json_encode([
        'success' => true,
        'invalid' => array_values($invalid)
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}