<?php
declare(strict_types=1);
ini_set('display_errors', 0); // Disable error display in production
header('Content-Type: application/json');

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/includes/session.php';
require_once $rootPath . '/db/connection.php';

try {
    // Validate request
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data', 400);
    }

    if (empty($input['message']) || !is_string($input['message'])) {
        throw new Exception('Invalid message', 400);
    }

    // Get user info
    $stmt = $con->prepare("SELECT name, muted FROM usertable WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        throw new Exception('User not found', 403);
    }
    
    if ($user['muted'] == 1) {
        throw new Exception('You are muted', 403);
    }
    
    // Process message
    $message = trim($input['message']);
    $filteredMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    
    // Insert message
    $stmt = $con->prepare("INSERT INTO messages (username, message) VALUES (?, ?)");
    $stmt->bind_param("ss", $user['name'], $filteredMessage);
    $stmt->execute();
    $messageId = $con->insert_id;
    $stmt->close();
    
    // Process mentions
    preg_match_all('/@(\w+)/', $message, $matches);
    $mentionedUsers = array_unique($matches[1]);
    
    if (!empty($mentionedUsers)) {
        $stmt = $con->prepare("INSERT INTO mentions (message_id, username, mentioned_user) VALUES (?, ?, ?)");
        
        // Verify each mention exists
        $checkStmt = $con->prepare("SELECT name FROM usertable WHERE name = ?");
        foreach ($mentionedUsers as $mentionedUser) {
            $checkStmt->bind_param("s", $mentionedUser);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $stmt->bind_param("iss", $messageId, $user['name'], $mentionedUser);
                $stmt->execute();
            }
        }
        $checkStmt->close();
        $stmt->close();


        // When processing chat messages
        function createMentionNotification($mentionedUserId, $mentioningUser, $chatId) {
            $db = getDatabaseConnection();
            $stmt = $db->prepare("
                INSERT INTO user_notifications (user_id, type, title, message)
                VALUES (?, 'mention', 'You were mentioned', ?)
            ");
            $message = "{$mentioningUser} mentioned you in a chat message";
            $stmt->execute([$mentionedUserId, $message]);
            
            // You might also want to include a link to the chat
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => $filteredMessage,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}