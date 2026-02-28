<?php
// Enable output buffering
ob_start();

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once './includes/session.php';
require_once './includes/functions.php';
require_once './db/connection.php';
require_once './includes/logging.php';
require_once './includes/get_user_info.php';

// Authentication checks
requireAuth();
requireMember();

// Default paths
$default_avatar = '/assets/avatars/default-avatar.png';
$default_banner = '/assets/banners/default-banner.png';

// Initialize variables
$profile_user = null;
$is_own_profile = false;
$error_message = '';
$success_message = '';
$view_count = 0;
$last_visitors = [];
$user_posts = [];
$post_replies = [];
$stored_post_content = '';

// Get requested username first
$requested_username = isset($_GET['u']) ? trim($_GET['u']) : '';

// Check for stored post errors from redirect
if (isset($_SESSION['post_error'])) {
    $error_message = $_SESSION['post_error'];
    unset($_SESSION['post_error']);
}

// Check for stored post content
if (isset($_SESSION['post_content'])) {
    $stored_post_content = $_SESSION['post_content'];
    unset($_SESSION['post_content']);
}

// Fetch profile data
try {
    $stmt = $con->prepare("SELECT * FROM usertable WHERE name = ?");
    $stmt->bind_param("s", $requested_username);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile_user = $result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("Profile data error: " . $e->getMessage());
    $error_message = "An error occurred while loading the profile.";
}

// Check if profile exists
if (!$profile_user) {
    // Load current user's profile data to display
    try {
        $current_user_stmt = $con->prepare("SELECT * FROM usertable WHERE id = ?");
        $current_user_stmt->bind_param("i", $_SESSION['user_id']);
        $current_user_stmt->execute();
        $current_user_result = $current_user_stmt->get_result();
        $profile_user = $current_user_result->fetch_assoc();
        $current_user_stmt->close();
        
        // Set flag to show the "profile not found" modal
        $show_profile_not_found_modal = true;
        $requested_profile_not_found = $requested_username;
    } catch (Exception $e) {
        error_log("Current user profile error: " . $e->getMessage());
        $error_message = "An error occurred while loading your profile.";
    }
} else {
    $is_own_profile = ($_SESSION['user_name'] === $profile_user['name']);
    
    // Record profile view (if not own profile)
    if (!$is_own_profile) {
        try {
            $viewer_id = $_SESSION['user_id'];
            $profile_id = $profile_user['id'];
            $stmt = $con->prepare("INSERT INTO profile_views (viewer_id, profile_id, viewed_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $viewer_id, $profile_id);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Profile view recording error: " . $e->getMessage());
        }
    }
    
    // Get profile view count
    try {
        $stmt = $con->prepare("SELECT COUNT(*) as count FROM profile_views WHERE profile_id = ?");
        $stmt->bind_param("i", $profile_user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $view_count = $result->fetch_assoc()['count'] ?? 0;
        $stmt->close();
    } catch (Exception $e) {
        error_log("Profile view count error: " . $e->getMessage());
    }
    
    // Get last 5 visitors
    try {
        $stmt = $con->prepare("
            SELECT u.name as username, pv.viewed_at 
            FROM profile_views pv
            JOIN usertable u ON pv.viewer_id = u.id
            WHERE pv.profile_id = ? AND pv.viewer_id != ?
            ORDER BY pv.viewed_at DESC
            LIMIT 5
        ");
        $profile_id = $profile_user['id'];
        $stmt->bind_param("ii", $profile_id, $profile_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $last_visitors[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Last visitors error: " . $e->getMessage());
    }
    
    // Get user posts
    try {
        $stmt = $con->prepare("
            SELECT p.*, u.name as username, u.profile_picture as avatar 
            FROM posts p
            JOIN usertable u ON p.user_id = u.id
            WHERE p.profile_id = ?
            ORDER BY p.created_at DESC
            LIMIT 10
        ");
        $profile_id = $profile_user['id'];
        $stmt->bind_param("i", $profile_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $user_posts[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("User posts error: " . $e->getMessage());
    }
    
    // Get post replies
    if (!empty($user_posts)) {
        $post_ids = array_column($user_posts, 'id');
        $placeholders = implode(',', array_fill(0, count($post_ids), '?'));
        
        try {
            $stmt = $con->prepare("
            SELECT r.*, u.name as username, u.profile_picture as avatar 
            FROM post_replies r
            JOIN usertable u ON r.user_id = u.id
            WHERE r.post_id IN ($placeholders)
            ORDER BY r.created_at ASC
        ");
            $types = str_repeat('i', count($post_ids));
            $stmt->bind_param($types, ...$post_ids);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $post_replies[$row['post_id']][] = $row;
            }
            $stmt->close();
        } catch (Exception $e) {
            error_log("Post replies error: " . $e->getMessage());
        }
    }
}

// Handle POST requests after profile data is loaded
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Profile picture upload
    if (isset($_FILES['profile_picture'])) {
        $result = handleFileUpload(
            $_FILES['profile_picture'], 
            './assets/avatars/', 
            ['image/jpeg', 'image/png', 'image/gif'], 
            2 * 1024 * 1024, // 2MB
            'profile_picture'
        );
        
        if ($result['success']) {
            $success_message = "Profile picture updated successfully!";
            $_SESSION['profile_picture'] = $result['path'];
        } else {
            $error_message = $result['error'];
        }
    }
    
    // Banner image upload
    if (isset($_FILES['banner_image'])) {
        $result = handleFileUpload(
            $_FILES['banner_image'], 
            './assets/banners/', 
            ['image/jpeg', 'image/png'], 
            5 * 1024 * 1024, // 5MB
            'banner_url'
        );
        
        if ($result['success']) {
            $success_message = "Banner image updated successfully!";
        } else {
            $error_message = $result['error'];
        }
    }
    
    // Handle post submission
    if (isset($_POST['post_content'])) {
        $post_content = trim($_POST['post_content']);
        if (!empty($post_content)) {
            try {
                // Ensure we have a valid profile user
                if (!$profile_user) {
                    throw new Exception("Profile not found");
                }
                
                $user_id = $_SESSION['user_id'];
                $profile_id = $profile_user['id'];
                
                // Debug logging
                error_log("Creating post: user_id=$user_id, profile_id=$profile_id, content=".substr($post_content, 0, 50));
                
                $stmt = $con->prepare("INSERT INTO posts (user_id, profile_id, content, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->bind_param("iis", $user_id, $profile_id, $post_content);
                
                if (!$stmt->execute()) {
                    throw new Exception("Database error: " . $stmt->error);
                }
                
                $stmt->close();
                
                // Redirect to the same profile page
                header("Location: profile?u=" . urlencode($profile_user['name']));
                exit();
            } catch (Exception $e) {
                error_log("Post creation error: " . $e->getMessage());
                $_SESSION['post_error'] = $e->getMessage();
                $_SESSION['post_content'] = $post_content;
                header("Location: profile?u=" . urlencode($requested_username));
                exit();
            }
        }
    }
    
    // Handle reply submission
    if (isset($_POST['reply_content']) && isset($_POST['post_id'])) {
        $reply_content = trim($_POST['reply_content']);
        $post_id = (int)$_POST['post_id'];
        
        if (!empty($reply_content) && $post_id > 0) {
            try {
                $user_id = $_SESSION['user_id'];
                $stmt = $con->prepare("INSERT INTO post_replies (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->bind_param("iis", $post_id, $user_id, $reply_content);
                $stmt->execute();
                $stmt->close();
                
                header("Location: profile?u=" . urlencode($profile_user['name']));
                exit();
            } catch (Exception $e) {
                error_log("Reply creation error: " . $e->getMessage());
                $error_message = "Failed to create reply. Please try again.";
            }
        }
    }
}

// Redirect to current user's profile if no username specified or empty username
if (!isset($_GET['u']) || trim($_GET['u']) === '') {
    // No user specified - show own profile
    if (isset($_SESSION['user_name'])) {
        header("Location: profile?u=" . urlencode($_SESSION['user_name']));
        exit();
    } else {
        // Shouldn't happen since we have requireAuth() but just in case
        header("Location: login.php");
        exit();
    }
}
// Get requested username
$requested_username = trim($_GET['u']);

ob_end_flush();

// File upload handler function
function handleFileUpload($file, $upload_dir, $allowed_types, $max_size, $field_name) {
    global $con, $_SESSION;
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => "File upload error. Please try again."];
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => "Invalid file type. Allowed types: " . implode(', ', $allowed_types)];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => "File too large. Maximum size: " . formatBytes($max_size)];
    }
    
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = $field_name . '_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
    $file_path = $upload_dir . $file_name;
    
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => false, 'error' => "Failed to upload file. Please try again."];
    }
    
    $relative_path = '/' . trim($upload_dir, './') . '/' . $file_name;
    
    try {
        $stmt = $con->prepare("UPDATE usertable SET $field_name = ? WHERE id = ?");
        $stmt->bind_param("si", $relative_path, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        
        return ['success' => true, 'path' => $relative_path];
    } catch (Exception $e) {
        error_log("$field_name update error: " . $e->getMessage());
        return ['success' => false, 'error' => "Failed to update $field_name. Please try again."];
    }
}

// Helper function to format bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Get user awards
$user_awards = [];
try {
    if (!empty($profile_user['awards'])) {
        // For backward compatibility with old text field
        $user_awards = json_decode($profile_user['awards'], true) ?: [];
    } else {
        // New relational system
        $stmt = $con->prepare("
            SELECT a.* FROM awards a
            JOIN user_awards ua ON a.id = ua.award_id
            WHERE ua.user_id = ?
        ");
        $stmt->bind_param("i", $profile_user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $user_awards[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Awards fetch error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name; ?> • <?php echo $profile_user ? 'Profile of ' . htmlspecialchars($profile_user['name']) : 'Profile Not Found'; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.11.2/css/all.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/profile.js" defer></script>
    <script src="js/heartbeat.js" defer></script>
    <script src="js/notify.js" defer></script>
</head>
<body>
    <!-- ========== Left Sidebar Start ========== -->
    <?php include_once('./blades/sidebar/sidebar.php'); ?>
    <!-- ========== Left Sidebar Ends ========== -->
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <div class="breadcrumbs">
                    <a href="/" class="breadcrumb-item"><i class="fas fa-home"></i></a>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-current"><?php echo $profile_user ? htmlspecialchars($profile_user['name']) : 'Profile'; ?></span>
                </div>
            </div>

        <!-- ========== Left Sidebar Start ========== -->
        <?php include_once('./blades/notify/notify.php'); ?>
        <!-- ========== Left Sidebar Ends ========== -->

        </header>
        
        <div class="content-area-wrapper">
            <?php if ($error_message): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($profile_user): ?>
                <div class="profile-container">
                    <!-- Profile Header -->
                    <div class="profile-header">
                        <div class="profile-banner">
                            <img src="<?php echo !empty($profile_user['banner_url']) ? htmlspecialchars($profile_user['banner_url']) : $default_banner; ?>" alt="Profile banner" class="banner-image">
                            <?php if ($is_own_profile): ?>
                            <div class="upload-container" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0;">
                                <div class="upload-overlay" onclick="showUploadForm('banner')">
                                    <div class="upload-btn">
                                        <i class="fas fa-camera"></i> Change Banner
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="profile-info">
                            <div class="profile-avatar">
                                <div class="avatar-large upload-container">
                                    <?php if (!empty($profile_user['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($profile_user['profile_picture']); ?>" alt="Profile avatar">
                                    <?php else: ?>
                                        <img src="<?php echo $default_avatar; ?>" alt="Default avatar">
                                    <?php endif; ?>
                                    <?php if ($is_own_profile): ?>
                                    <div class="upload-overlay" onclick="showUploadForm('avatar')">
                                        <div class="upload-btn">
                                            <i class="fas fa-camera"></i> Change Photo
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="profile-meta">
                            <div class="profile-name">
                                <h2><?php echo htmlspecialchars($profile_user['name']); ?> 
                                    <span class="user-profile-status <?php echo (strtotime($profile_user['last_activity']) > time() - 300) ? 'online' : 'offline'; ?>"></span>
                                </h2>
                            </div>
                                <div class="profile-stats">
                                    <div class="stat-badge">
                                        <i class="fas fa-comment-alt"></i>
                                        <span><?php echo count($user_posts); ?> Posts</span>
                                    </div>
                                    <div class="stat-badge">
                                        <i class="fas fa-coins"></i>
                                        <span><?php echo $profile_user['balance'] ?? 0; ?> Coins</span>
                                    </div>
                                    <div class="stat-badge">
                                        <i class="fas fa-hashtag"></i>
                                        <span><?php echo $profile_user['id']; ?> UID</span>
                                    </div>
                                    <div class="stat-badge">
                                        <i class="fas fa-user-tag"></i>
                                        <span><?php echo ucfirst($profile_user['role']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Content -->
                    <div class="profile-content">
                        <!-- Left Column - Information + Awards -->
                        <div class="profile-left">
                            <div class="profile-box information-box">
                                <div class="box-header">
                                    <h3 class="profile-title">Information</h3>
                                </div>
                                <div class="box-content">
                                    <div class="info-item">
                                        <span class="info-label">Status:</span>
                                        <span class="info-value <?php echo (strtotime($profile_user['last_activity']) > time() - 300) ? 'online' : 'offline'; ?>">
                                            <?php echo (strtotime($profile_user['last_activity']) > time() - 300) ? 'Online' : 'Offline'; ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">UID:</span>
                                        <span class="info-value"><?php echo $profile_user['id']; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Joined:</span>
                                        <span class="info-value"><?php echo date('m-d-Y', strtotime($profile_user['created_at'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Last Login:</span>
                                        <span class="info-value"><?php echo $profile_user['last_login'] ? timeAgo($profile_user['last_login']) : 'Never'; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Last Activity:</span>
                                        <span class="info-value"><?php echo $profile_user['last_activity'] ? timeAgo($profile_user['last_activity']) : 'Never'; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Profile Views:</span>
                                        <span class="info-value"><?php echo number_format($view_count); ?> views</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Discord ID:</span>
                                        <span class="info-value"><?php echo !empty($profile_user['discordid']) ? htmlspecialchars($profile_user['discordid']) : 'Not Linked'; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Account Status:</span>
                                        <span class="info-value <?php echo $profile_user['status'] === 'verified' ? 'verified' : 'not-verified'; ?>">
                                            <?php echo ucfirst($profile_user['status']); ?>
                                        </span>
                                    </div>
                                    <?php if ($profile_user['banned']): ?>
                                    <div class="info-item">
                                        <span class="info-label">Account Status:</span>
                                        <span class="info-value banned">Banned</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="profile-box awards-box">
                                <div class="box-header">
                                    <h3 class="profile-title">Awards</h3>
                                </div>
                                <div class="box-content">
                                    <?php if (!empty($user_awards)): ?>
                                        <div class="awards-grid">
                                            <?php foreach ($user_awards as $award): ?>
                                                <div class="award-item" data-tooltip="<?php echo htmlspecialchars($award['name']); ?>">
                                                    <img src="<?php echo htmlspecialchars($award['icon_path'] ?? '/assets/awards/default.png'); ?>" 
                                                        title="<?php echo htmlspecialchars($award['description']); ?>" alt="<?php echo htmlspecialchars($award['name']); ?>"
                                                        class="award-icon">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-awards">
                                            <i class="fas fa-trophy"></i>
                                            <p>No awards yet</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="profile-box product-access-box">
                                <div class="box-header">
                                    <h3 class="profile-title">Product Access</h3>
                                </div>
                                <div class="box-content">
                                    <?php if (!empty($profile_user['product_access'])): ?>
                                        <div class="product-item">
                                            <i class="fas fa-check-circle"></i>
                                            <span><?php echo htmlspecialchars($profile_user['product_access']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <p class="no-products">No product access</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Middle Column - Post Wall -->
                        <div class="profile-middle">
                            <div class="profile-box post-wall">
                                <div class="box-header">
                                    <h3 class="profile-title">Post Wall</h3>
                                </div>
                                <div class="box-content">
                                    <?php if (!$is_own_profile): ?>
                                        <div class="post-form">
                                            <form method="POST" action="">
                                                <div class="post-form-input">
                                                    <textarea name="post_content" placeholder="Write something on this wall..." rows="3"><?php echo htmlspecialchars($stored_post_content); ?></textarea>
                                                    <div class="post-form-actions">
                                                        <button type="submit" class="post-button">
                                                            <i class="fas fa-paper-plane"></i> Post
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="posts-container">
                                        <?php if (!empty($user_posts)): ?>
                                            <?php foreach ($user_posts as $post): ?>
                                                <div class="post" data-post-id="<?php echo $post['id']; ?>">
    <div class="post-header">
        <div class="post-author">
            <div class="post-avatar">
                <?php if (!empty($post['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($post['avatar']); ?>" alt="User avatar">
                <?php else: ?>
                    <img src="<?php echo $default_avatar; ?>" alt="Default avatar">
                <?php endif; ?>
            </div>
            <div class="post-meta">
                <a href="profile?u=<?php echo urlencode($post['username']); ?>" class="post-username">
                    <?php echo htmlspecialchars($post['username']); ?>
                </a>
                <span class="post-date"> • <?php echo date('M j Y, g:i A', strtotime($post['created_at'])); ?></span>
            </div>
        </div>
    </div>
    <div class="post-content">
        <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
    </div>
    <div class="post-actions">
        <button class="post-action reply-button">
            <i class="fas fa-reply"></i> Reply
        </button>
        <?php if (!empty($post_replies[$post['id']])): ?>
            <span class="reply-count"><?php echo count($post_replies[$post['id']]); ?> replies</span>
        <?php endif; ?>
    </div>
    
    <div class="replies-container">
        <?php if (!empty($post_replies[$post['id']])): ?>
            <?php foreach ($post_replies[$post['id']] as $reply): ?>
    <div class="reply">
        <div class="post-author">
            <div class="post-avatar">
                <?php if (!empty($reply['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($reply['avatar']); ?>" alt="User avatar">
                <?php else: ?>
                    <img src="<?php echo $default_avatar; ?>" alt="Default avatar">
                <?php endif; ?>
            </div>
            <div class="post-meta">
                <a href="profile?u=<?php echo urlencode($reply['username']); ?>" class="post-username">
                    <?php echo htmlspecialchars($reply['username']); ?>
                </a>
                <span class="post-date"> • <?php echo date('M j Y, g:i A', strtotime($reply['created_at'])); ?></span>
            </div>
        </div>
        <div class="reply-content">
            <p><?php echo nl2br(htmlspecialchars($reply['content'])); ?></p>
        </div>
    </div>
<?php endforeach; ?>
        <?php endif; ?>
        
        <!-- This is the crucial addition - the reply form for ALL posts -->
        <div class="reply-form" style="display: none;">
            <form method="POST" action="">
                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                <textarea name="reply_content" placeholder="Write your reply..." rows="2"></textarea>
                <div class="reply-buttons">
                    <button type="button" class="cancel-reply">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="submit-reply">
                        <i class="fas fa-paper-plane"></i> Reply
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="post-placeholder">
                                                <i class="fas fa-comment-alt fa-3x"></i>
                                                <p>No posts yet</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column - Last Visitors -->
                        <div class="profile-right">
                            <div class="profile-box last-visitors-box">
                                <div class="box-header">
                                    <h3 class="profile-title">Last Visitors</h3>
                                </div>
                                <div class="box-content">
                                    <?php if (!empty($last_visitors)): ?>
                                        <?php foreach ($last_visitors as $visitor): ?>
                                            <div class="visitor-item">
                                                <a href="profile?u=<?php echo urlencode($visitor['username']); ?>" class="visitor-link">
                                                    <span class="visitor-name"><?php echo htmlspecialchars($visitor['username']); ?></span>
                                                </a>
                                                <span class="visitor-time"><?php echo timeAgo($visitor['viewed_at']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="no-visitors">No visitors yet</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                    <p style="margin-top: 10px;"><a href="dashboard.php" class="btn-return">Return to Dashboard</a></p>
                </div>
            <?php endif; ?>
        </div>
        
        <footer class="main-footer">
            <p>
                &copy; <?php echo date('Y'); ?> <?php echo $site_name; ?>. All rights reserved. | 
                <span class="badge">
                    <i class="fas fa-code"></i> CompileCrew
                </span>
            </p>
        </footer>
    </main>

    <!-- Profile Not Found Modal -->
<?php if (isset($show_profile_not_found_modal) && $show_profile_not_found_modal): ?>
<div id="profile-not-found-modal" class="modal" style="display: block;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Profile Not Found</h3>
            <span class="close-modal" onclick="document.getElementById('profile-not-found-modal').style.display='none'">&times;</span>
        </div>
        <div class="modal-body">
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> 
                The member "<?php echo htmlspecialchars($requested_profile_not_found); ?>" you specified is either invalid or doesn't exist.
            </div>
            <div class="search-container">
                <h4>Search for another member</h4>
                <form action="profile" method="get" class="user-search-form">
                    <div class="search-input">
                        <input type="text" name="u" placeholder="Enter username..." required>
                        <button type="submit"><i class="fas fa-search"></i> Search</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

    <!-- Upload Forms (hidden by default) -->
    <?php if ($is_own_profile): ?>
    <div id="avatar-upload-form" class="upload-form">
        <h3>Change Profile Picture</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/gif" required>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="hideUploadForm('avatar')">Cancel</button>
                <button type="submit" class="btn-upload">Upload</button>
            </div>
        </form>
    </div>

    <div id="banner-upload-form" class="upload-form">
        <h3>Change Banner Image</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="banner_image" accept="image/jpeg,image/png" required>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="hideUploadForm('banner')">Cancel</button>
                <button type="submit" class="btn-upload">Upload</button>
            </div>
        </form>
    </div>

    <div id="form-overlay" class="form-overlay" onclick="hideAllUploadForms()"></div>
    <?php endif; ?>
</body>
</html>