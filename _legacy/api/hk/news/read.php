<?php
require_once '../../../includes/session.php';
require_once '../../../db/connection.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

try {
    // Authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    // Validate input
    $newsId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$newsId) {
        throw new Exception('Invalid news ID', 400);
    }

    // Get news article
    $stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
    $stmt->execute([$newsId]);
    $news = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$news) {
        throw new Exception('News article not found', 404);
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'news' => $news
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}