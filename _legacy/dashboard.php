<?php
// Enable output buffering
ob_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once './includes/session.php'; // This handles session_start()
require_once './includes/functions.php';
require_once './db/connection.php';
require_once './includes/logging.php';
require_once 'includes/get_user_info.php';


// Ensure the user is authenticated
requireAuth();

// Ensure the user is allowed accesssing the page
requireMember();

// Check if the user is banned
//checkIfBanned();

// Fetch statistics from database
$stats = [
    'total_users' => 0,
    'total_downloads' => 0,
    'total_products' => 0,
    'total_messages' => 0
];

try {
    
    // Get total users
    $stmt = $con->query("SELECT COUNT(*) as count FROM usertable");
    $result = $stmt->fetch_assoc();
    $stats['total_users'] = $result['count'] ?? 0;

    // Get total downloads (assuming download_keys table exists)
    $stmt = $con->query("SELECT COUNT(*) as count FROM download_keys");
    $result = $stmt->fetch_assoc();
    $stats['total_downloads'] = $result['count'] ?? 0;

    // Get total products (assuming products table exists)
    $stmt = $con->query("SELECT COUNT(*) as count FROM products");
    $result = $stmt->fetch_assoc();
    $stats['total_products'] = $result['count'] ?? 0;

    // Get total messages (assuming messages table exists)
    $stmt = $con->query("SELECT COUNT(*) as count FROM messages");
    $result = $stmt->fetch_assoc();
    $stats['total_messages'] = $result['count'] ?? 0;
} catch (Exception $e) {
    // Log error but don't show to user
    error_log("Dashboard statistics error: " . $e->getMessage());
}

try {
    // Get online users count
    $stmt = $con->query("SELECT COUNT(*) as count FROM usertable WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $result = $stmt->fetch_assoc();
    $onlineUsers = $result['count'] ?? 0;
} catch (Exception $e) {
    $onlineUsers = 0;
    error_log("Online users error: " . $e->getMessage());
}


ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name;?> &bullet; Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.11.2/css/all.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/emoji-picker/1.1.5/css/emoji.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/emoji-picker/1.1.5/js/emoji-picker.min.js" defer></script>
    <script src="js/main.js" defer></script>
    <script src="js/chat.js" defer></script>
    <script src="js/heartbeat.js" defer></script>
    <script src="js/notify.js" defer></script>
</head>
<body data-username="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>">
    <div class="sidebar-overlay"></div>
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
                    <span class="breadcrumb-current">Dashboard</span>
                </div>
            </div>

        <!-- ========== Left Sidebar Start ========== -->
        <?php include_once('./blades/notify/notify.php'); ?>
        <!-- ========== Left Sidebar Ends ========== -->

        </header>
        
        <div class="content-area-wrapper">
    <!-- Stats Cards -->
    <section class="stats-section">
        <div class="stats-card">
            <div class="stats-icon users">
                <i class="fas fa-users"></i>
            </div>
            <div class="stats-info">
                <h4>Total Users</h4>
                <p class="stats-value"><?php echo number_format($stats['total_users']); ?></p>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon downloads">
                <i class="fas fa-download"></i>
            </div>
            <div class="stats-info">
                <h4>Total Downloads</h4>
                <p class="stats-value"><?php echo number_format($stats['total_downloads']); ?></p>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon products">
                <i class="fas fa-box"></i>
            </div>
            <div class="stats-info">
                <h4>Total Products</h4>
                <p class="stats-value"><?php echo number_format($stats['total_products']); ?></p>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon messages">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="stats-info">
                <h4>Total Messages</h4>
                <p class="stats-value"><?php echo number_format($stats['total_messages']); ?></p>
            </div>
        </div>
    </section>
            
            <!-- Content Area -->
            <section class="content-area">
                <div class="news-panel">    
                    <h2><i class="fas fa-newspaper"></i> Latest News</h2>
                    <div class="news-content">
                    <?php include_once('./blades/news/list_news.php'); ?>
                    </div>
                    <!-- <button class="btn-view-all-news">
                        <i class="fas fa-plus"></i> View All News
                    </button> -->
                </div>
                
                <div class="chat-panel">
                    <div class="chat-header">
                        <h2><i class="fas fa-comments"></i> Community Lounge</h2>
                        <div class="online-users">
                    <span class="online-count"><?php echo $onlineUsers; ?></span> online
                </div>
                    </div>
                    <div class="chat-messages" id="chat-messages">
                        <?php include_once('./blades/chat/list_chat.php'); ?>
                    </div>
                    <form id="chat-form" class="chat-input-container">
                        <button type="button" class="emoji-picker-btn">
                            <i class="far fa-smile"></i>
                        </button>
                        <div class="chat-input">
                            <input type="text" placeholder="Type a message..." id="chat-message">
                            <button type="submit" class="send-btn">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
        
    <!-- ========== Footer Start ========== -->
    <?php include_once('./blades/footer/footer.php'); ?>
    <!-- ========== Footer Ends ========== -->
    </main>

    <!-- Emoji Picker Container (hidden by default) -->
    <div id="emoji-picker-container" style="display: none;"></div>
</body>
</html>