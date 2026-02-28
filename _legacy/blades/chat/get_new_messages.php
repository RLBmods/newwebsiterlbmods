<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Calculate the root path (2 levels up from /blades/chat/)
$rootPath = dirname(__DIR__, 2);

// Include required files with absolute paths
require_once $rootPath . '/includes/session.php';
require_once $rootPath . '/includes/functions.php';
require_once $rootPath . '/includes/get_user_info.php';
require_once $rootPath . '/db/connection.php';

// Ensure the user is authenticated
if (!function_exists('requireAuth')) {
    die(json_encode(['error' => 'Authentication system not available']));
}
requireAuth();

// Database connection check
if (!isset($con) || !($con instanceof mysqli)) {
    die(json_encode(['error' => 'Database connection failed']));
}

// Get last message ID from request
$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
if ($lastId < 0) {
    die(json_encode(['error' => 'Invalid last message ID']));
}

/**
 * Calculate human-readable time difference
 */
function chat_time_ago($timestamp) {
    if (empty($timestamp)) {
        return 'just now';
    }

    $chat_time_ago = strtotime($timestamp);
    if ($chat_time_ago === false) {
        return 'recently';
    }

    $current_time = time();
    $time_difference = $current_time - $chat_time_ago;

    $seconds = $time_difference;
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2592000);
    $years = round($seconds / 31536000);

    if ($seconds <= 60) {
        return 'just now';
    } elseif ($minutes <= 60) {
        return $minutes === 1 ? '1 minute ago' : "$minutes minutes ago";
    } elseif ($hours <= 24) {
        return $hours === 1 ? '1 hour ago' : "$hours hours ago";
    } elseif ($days <= 7) {
        return $days === 1 ? '1 day ago' : "$days days ago";
    } elseif ($weeks <= 4) {
        return $weeks === 1 ? '1 week ago' : "$weeks weeks ago";
    } elseif ($months <= 12) {
        return $months === 1 ? '1 month ago' : "$months months ago";
    } else {
        return $years === 1 ? '1 year ago' : "$years years ago";
    }
}

/**
 * Sanitize chat message output
 */
function sanitize_chat_message($message) {
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    // Allow basic formatting (optional)
    $message = preg_replace('/\b(https?:\/\/\S+)\b/', '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', $message);
    return nl2br($message);
}

try {
    // Fetch only new messages
    $sql = "SELECT m.*, u.role, u.profile_picture 
            FROM messages m 
            LEFT JOIN usertable u ON m.username = u.name 
            WHERE m.id > ? AND m.deleted = 0
            ORDER BY m.id ASC";
    
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $lastId);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!empty($messages)) {
        foreach ($messages as $message) {
            $username = htmlspecialchars($message['username'] ?? 'Unknown');
            $role = ($message['role'] ?? 'guest');
            $avatar = !empty($message['profile_picture']) ? 
                htmlspecialchars($message['profile_picture']) : 
                'default-avatar.png';
            $timeAgo = chat_time_ago($message['timestamp']);
            
            // Process message content
            if ($message['deleted']) {
                $messageText = '<em>[This message has been removed by moderator]</em>';
            } else {
                $messageText = preg_replace_callback(
                    '/@(\w+)/',
                    function($matches) {
                        return '<span class="mention">@' . htmlspecialchars($matches[1]) . '</span>';
                    },
                    sanitize_chat_message($message['message'] ?? '')
                );
            };


            if (strtoupper($username) === 'SYSTEM') {
                echo <<<HTML
                <div class="message system-message" data-id="{$message['id']}">
                    <div class="message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">
                        <div class="message-header">
                            <span class="message-role system">SYSTEM</span>
                            <span class="message-time">{$timeAgo}</span>
                        </div>
                        <div class="message-text">{$messageText}</div>
                    </div>
                </div>
                HTML;
            } else {
                echo <<<HTML
                <div class="message" data-id="{$message['id']}">
                    <div class="message-avatar">
                        <img src="{$avatar}" alt="{$username}'s avatar" onerror="this.src='/assets/avatars/default-avatar.png'">
                    </div>
                    <div class="message-content">
                        <div class="message-header">
                            <span class="message-role {$role}">{$role}</span>
                            <span class="message-user">{$username}</span>
                            <span class="message-time">{$timeAgo}</span>
                        </div>
                        <div class="message-text">{$messageText}</div>
                    </div>
                </div>
                HTML;
            }
        }
    }

} catch (Exception $e) {
    error_log("Chat Error: " . $e->getMessage());
}
?>