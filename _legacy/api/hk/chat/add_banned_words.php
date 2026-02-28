<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../../db/connection.php';
require_once '../../../vendor/autoload.php';
require_once '../../../includes/get_user_info.php';
require_once '../../../includes/session.php';
require_once '../../../includes/logging.php';

requireAuth();
requireStaff();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['word'])) {
    echo json_encode(['success' => false, 'message' => 'Word required']);
    exit;
}

$word = $input['word'];

try {
    $stmt = $con->prepare("INSERT INTO banned_words (word) VALUES (?)");
    $stmt->bind_param("s", $word);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // If duplicate word, ignore the error
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode(['success' => false, 'message' => 'Word already exists']);
    } else {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>