<?php
header('Content-Type: application/json');
// Include required files
require_once '../../../includes/session.php';
require_once '../../../includes/functions.php';
require_once '../../../db/connection.php';
require_once '../../../includes/logging.php';
require_once '../../../includes/get_user_info.php';

// Authentication
requireAuth();
requireStaff();

$response = ['success' => false, 'error' => ''];

try {
    if (empty($username)) {
        throw new Exception('User identity verification failed');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }

    $title = htmlspecialchars(trim($input['title'] ?? ''));
    $content = htmlspecialchars(trim($input['content'] ?? ''));
    $author = $username;

    if (empty($title) || empty($content)) {
        throw new Exception('Title and content are required');
    }

    $stmt = $pdo->prepare("INSERT INTO news (title, content, author, date) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$title, $content, $author]);

    $response = [
        'success' => true,
        'message' => 'News article created successfully',
        'news' => [
            'id' => $pdo->lastInsertId(),
            'title' => $title,
            'content' => $content,
            'author' => $author,
            'date' => date('Y-m-d H:i:s')
        ]
    ];

} catch (Exception $e) {
    error_log("News creation error: " . $e->getMessage());
    $response['error'] = $e->getMessage();
    http_response_code(400);
}

ob_clean();
echo json_encode($response);
exit;