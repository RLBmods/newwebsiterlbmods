<?php
header('Content-Type: application/json');
require_once '../../../includes/session.php';
require_once '../../../includes/functions.php';
require_once '../../../db/connection.php';
require_once '../../../includes/logging.php';

requireAuth();
requireStaff();

$response = ['success' => false, 'error' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }

    $banId = $input['id'] ?? null;
    $expiresAt = $input['expires_at'] ?? null;

    if (!$banId || !$expiresAt) {
        throw new Exception('Missing required fields');
    }

    // Verify ban exists and is active
    $stmt = $pdo->prepare("SELECT id FROM bans WHERE id = ? AND (is_permanent = 0 AND expires_at > NOW())");
    $stmt->execute([$banId]);
    if (!$stmt->fetch()) {
        throw new Exception('Ban not found or not extendable');
    }

    // Update ban expiration
    $stmt = $pdo->prepare("UPDATE bans SET expires_at = ? WHERE id = ?");
    $stmt->execute([$expiresAt, $banId]);

    // Log action
    logAction($pdo, $_SESSION['user_id'], 'Extended ban', "Extended ban ID: $banId");

    $response = [
        'success' => true,
        'message' => 'Ban extended successfully',
        'expires_at' => $expiresAt
    ];

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    http_response_code(400);
}

echo json_encode($response);
exit;