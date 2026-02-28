<?php
header('Content-Type: application/json');
require_once '../../../includes/session.php';
require_once '../../../includes/functions.php';
require_once '../../../db/connection.php';
require_once '../../../includes/logging.php';
require_once '../../../includes/get_user_info.php';

requireAuth();
requireStaff();

$response = ['success' => false, 'error' => ''];

try {
    // Validate session username exists
    if (empty($username)) {
        throw new Exception('User identity verification failed');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }

    $newsId = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$newsId) {
        throw new Exception('Invalid news ID');
    }

    $title = htmlspecialchars(trim($input['title'] ?? ''));
    $content = htmlspecialchars(trim($input['content'] ?? ''));

    if (empty($title) || empty($content)) {
        throw new Exception('Title and content are required');
    }

    $stmt = $pdo->prepare("UPDATE news SET title = ?, content = ? WHERE id = ?");
    $stmt->execute([$title, $content, $newsId]);

    $response = [
        'success' => true,
        'message' => 'News article updated successfully'
    ];

} catch (Exception $e) {
    error_log("News update error: " . $e->getMessage());
    $response['error'] = $e->getMessage();
    http_response_code(400);
}

ob_clean();
echo json_encode($response);
exit;