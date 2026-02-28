<?php
require_once __DIR__ . '/../db/connection.php';

function sanitizeInput($input) {
    global $con;
    return $con->real_escape_string(htmlspecialchars($input));
}

function fetchUser($email) {
    global $con;
    $query = $con->prepare("SELECT * FROM usertable WHERE email = ?");
    $query->bind_param("s", $email);
    $query->execute();
    return $query->get_result()->fetch_assoc();
}

function registerUser($name, $email, $password) {
    global $con;
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $query = $con->prepare("INSERT INTO usertable (name, email, password, status, role) VALUES (?, ?, ?, 'notverified', 'member')");
    $query->bind_param("sss", $name, $email, $hashedPassword);
    return $query->execute();
}

function linkDiscord($user_id) {
    global $con;

    // Step 1: Generate the URL for Discord OAuth2
    $client_id = 'YOUR_DISCORD_CLIENT_ID';
    $redirect_uri = urlencode('http://yourdomain.com/discord_oauth.php');  // Update this with your actual redirect URI
    $state = bin2hex(random_bytes(16)); // CSRF protection

    $_SESSION['discord_state'] = $state; // Store the state in session for validation

    // Generate the Discord OAuth2 URL
    $discord_url = "https://discord.com/oauth2/authorize?client_id=$client_id&redirect_uri=$redirect_uri&response_type=code&scope=identify&state=$state";
    header("Location: $discord_url");
    exit();
}

function unlinkDiscord($user_id) {
    global $con;

    // Step 2: Unlink Discord by removing the discordid from the database
    $stmt = $con->prepare("UPDATE usertable SET discordid = NULL WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

function siteinformation() {
    // Fetch Site Settings
    $sql = "SELECT * FROM site_settings LIMIT 1";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);

    $site_name = $row['site_name'];
    $site_logo = $row['logo'];
    $copyright = $row['copyright'];
    $site_icon = $row['favicon'];
}

if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        // Calculate periods
        $periods = [
            'year'   => $diff->y,
            'month'  => $diff->m,
            'week'   => floor($diff->d / 7),
            'day'    => $diff->d % 7,
            'hour'   => $diff->h,
            'minute' => $diff->i,
            'second' => $diff->s
        ];

        // Build parts array
        $parts = [];
        foreach ($periods as $unit => $value) {
            if ($value > 0) {
                $parts[] = $value.' '.$unit.($value > 1 ? 's' : '');
            }
        }

        // Return appropriate string
        if (!$full) $parts = array_slice($parts, 0, 1);
        return $parts ? implode(', ', $parts).' ago' : 'just now';
    }
}
/**
 * Format time as "X time ago"
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $timeDiff = time() - $time;
    
    if ($timeDiff < 60) {
        return "just now";
    } elseif ($timeDiff < 3600) {
        $mins = floor($timeDiff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($timeDiff < 86400) {
        $hours = floor($timeDiff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($timeDiff < 2592000) {
        $days = floor($timeDiff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date('M j, Y', $time);
    }
}

/**
 * Format online time in days, hours, minutes
 */
function formatOnlineTime($seconds) {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    $parts = [];
    if ($days > 0) $parts[] = $days . " day" . ($days > 1 ? "s" : "");
    if ($hours > 0) $parts[] = $hours . " hour" . ($hours > 1 ? "s" : "");
    if ($minutes > 0) $parts[] = $minutes . " minute" . ($minutes > 1 ? "s" : "");
    
    return implode(", ", $parts) ?: "0 minutes";
}


function logActivity($userId, $type, $description) {
    global $con;
    
    error_log("Attempting to log activity: $userId, $type, $description"); // Debug logging
    
    try {
        $stmt = $con->prepare("INSERT INTO activities (user_id, type, description) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userId, $type, $description);
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("Activity log failed: " . $stmt->error);
            return false;
        }
        
        error_log("Activity logged successfully. ID: " . $stmt->insert_id);
        return $stmt->insert_id;
    } catch (Exception $e) {
        error_log("Activity log exception: " . $e->getMessage());
        return false;
    }
}
?>
