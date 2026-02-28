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
    die("Authentication functions not available");
}
requireAuth();

// Database connection check
if (!isset($con) || !($con instanceof mysqli)) {
    die("Database connection failed");
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

/**
 * Get correct avatar path
 */
function get_avatar_path($profile_picture) {
    // If it's already a full URL or path, return as-is
    if (filter_var($profile_picture, FILTER_VALIDATE_URL) || strpos($profile_picture, '/') === 0) {
        return $profile_picture;
    }
    
    // Default to /assets/avatars/ directory if no path is specified
    return $profile_picture ?: '';
}

try {
    // Fetch the latest messages with user details
    $limit = 30;
    $sql = "SELECT m.*, u.role, u.profile_picture, u.status 
            FROM messages m 
            LEFT JOIN usertable u ON m.username = u.name 
            WHERE m.deleted = 0
            ORDER BY m.id DESC 
            LIMIT ?";
    
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($messages)) {
        echo '<div class="no-messages"><i class="fas fa-comment-slash"></i> No messages yet. Be the first to chat!</div>';
    } else {
        foreach (array_reverse($messages) as $message) {
            $username = htmlspecialchars($message['username'] ?? 'Unknown');
            $role = ($message['role'] ?? 'guest');
            $status = ($message['status'] ?? 'offline');
            $avatar = get_avatar_path($message['profile_picture'] ?? '');
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

            // System message
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
                // User message
                echo <<<HTML
                <div class="message" data-id="{$message['id']}">
                    <div class="message-avatar">
                        <img src="{$avatar}" alt="{$username}'s avatar" onerror="this.src='/assets/avatars/default-avatar.png'">
                        <span class="user-status {$status}"></span>
                    </div>
                    <div class="message-content">
                        <div class="message-header">
                            <span class="message-role {$role}">{$role}</span>
                            <a href="/profile.php?u={$username}" class="message-user">{$username}</a>
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
    echo '<div class="chat-error"><i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Add some basic CSS
echo <<<CSS
<style>
    .message {
        display: flex;
        padding: 10px;
        animation: fadeIn 0.3s ease-in;
    }
    
    .message-avatar {
        position: relative;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 10px;
        overflow: hidden;
        background: #f0f0f0;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .message-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .user-status {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        border: 2px solid white;
    }
    
    .user-status.online { background: #2ecc71; }
    .user-status.offline { background: #95a5a6; }
    .user-status.idle { background: #f39c12; }
    .user-status.dnd { background: #e74c3c; }
    
    .message-content {
        flex: 1;
        min-width: 0;
    }
    
    .message-header {
        display: flex;
        align-items: center;
        margin-bottom: 5px;
        font-size: 0.9em;
        flex-wrap: wrap;
        gap: 6px;
    }
    
    .message-role {
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 0.8em;
        font-weight: bold;
        text-transform: capitalize;
    }
    
    .message-role.founder { background: #ff6b6b; color: white; }
    .message-role.manager { background: #ff6b6b; color: white; }
    .message-role.developer { background: #ff6b6b; color: white; }
    .message-role.admin { background: #ff6b6b; color: white; }
    .message-role.support { background: #48dbfb; color: white; }
    .message-role.customer { background: #feca57; color: white; }
    .message-role.member { background: #1dd1a1; color: white; }
    .message-role.guest { background: #576574; color: white; }
    .message-role.system { background: #5f27cd; color: white; }
    
    .message-user {
        font-weight: bold;
        color: inherit;
        text-decoration: none;
    }
    
    .message-user:hover {
        text-decoration: underline;
    }
    
    .message-time {
        color: #999;
        font-size: 0.8em;
    }
    
    .message-text {
        word-wrap: break-word;
    }
    
    .mention {
        background-color: rgba(29, 155, 209, 0.1);
        padding: 0 2px;
        border-radius: 3px;
        font-weight: bold;
    }
    
    .no-messages, .chat-error {
        text-align: center;
        padding: 20px;
        color: #666;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
CSS;
?>