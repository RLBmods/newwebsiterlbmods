<?php
// Enable error reporting (for development only)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
ob_start(); // Start output buffering

require_once '../../config.php';
require_once '../../includes/session.php';

try {
    // Authenticate the user
    $user = getAuthenticatedUser();
    if (!$user) {
        throw new Exception('Unauthorized', 401);
    }

    // Verify database connection
    global $con;
    if (!$con || !$con->ping()) {
        throw new Exception('Database connection failed', 500);
    }

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Get recent notifications
            $stmt = $con->prepare("
                SELECT id, type, title, message, is_read, created_at
                FROM user_notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 10
            ");
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $con->error, 500);
            }
            
            $stmt->bind_param("i", $user['id']);
            if (!$stmt->execute()) {
                throw new Exception('Database execute failed: ' . $stmt->error, 500);
            }
            
            $result = $stmt->get_result();
            $notifications = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Count unread notifications
            $stmt = $con->prepare("
                SELECT COUNT(*) AS unread_count 
                FROM user_notifications 
                WHERE user_id = ? AND is_read = FALSE
            ");
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $con->error, 500);
            }
            
            $stmt->bind_param("i", $user['id']);
            if (!$stmt->execute()) {
                throw new Exception('Database execute failed: ' . $stmt->error, 500);
            }
            
            $stmt->bind_result($unreadCount);
            $stmt->fetch();
            $stmt->close();

            echo json_encode([
                'success' => true,
                'notifications' => $notifications ?: [],
                'unreadCount' => (int)$unreadCount
            ]);
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON input', 400);
            }

            if (isset($input['mark_all_read']) && $input['mark_all_read']) {
                $stmt = $con->prepare("
                    UPDATE user_notifications 
                    SET is_read = TRUE 
                    WHERE user_id = ?
                ");
                if (!$stmt) {
                    throw new Exception('Database prepare failed: ' . $con->error, 500);
                }
                
                $stmt->bind_param("i", $user['id']);
                if (!$stmt->execute()) {
                    throw new Exception('Database execute failed: ' . $stmt->error, 500);
                }
                
                $stmt->close();
                echo json_encode(['success' => true]);
            } 
            elseif (isset($input['notification_id'])) {
                $stmt = $con->prepare("
                    UPDATE user_notifications 
                    SET is_read = TRUE 
                    WHERE id = ? AND user_id = ?
                ");
                if (!$stmt) {
                    throw new Exception('Database prepare failed: ' . $con->error, 500);
                }
                
                $stmt->bind_param("ii", $input['notification_id'], $user['id']);
                if (!$stmt->execute()) {
                    throw new Exception('Database execute failed: ' . $stmt->error, 500);
                }
                
                $stmt->close();
                echo json_encode(['success' => true]);
            } 
            else {
                throw new Exception('Invalid request parameters', 400);
            }
            break;

        default:
            throw new Exception('Method not allowed', 405);
    }
} catch (Exception $e) {
    ob_end_clean(); // Clear any output
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}

ob_end_flush();
?>