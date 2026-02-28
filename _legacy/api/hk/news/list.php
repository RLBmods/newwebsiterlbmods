<?php
header('Content-Type: application/json');
require_once '../../../includes/session.php';
require_once '../../../includes/functions.php';
require_once '../../../db/connection.php';

$response = ['success' => false, 'news' => []];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    // Get all news articles, newest first
    $stmt = $pdo->query("SELECT * FROM news ORDER BY date DESC");
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        'success' => true,
        'news' => $news
    ];

} catch (Exception $e) {
    error_log("News list error: " . $e->getMessage());
    $response['error'] = $e->getMessage();
    http_response_code(400);
}

ob_clean();
echo json_encode($response);
exit;