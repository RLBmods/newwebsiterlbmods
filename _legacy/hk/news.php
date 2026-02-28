<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../db/connection.php';
require_once '../includes/logging.php';
require_once '../includes/get_user_info.php';

// Authentication
requireAuth();
requireStaff();

// Get user info
$userInfo = getUserInfo($_SESSION['user_id']);
if (!$userInfo) {
    header("Location: ../login.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RLBMODS • News Management</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/hk/dashboard.css">
    <link rel="stylesheet" href="/css/hk/news.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.11.2/css/all.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/heartbeat.js" defer></script>
    <script src="../js/notify.js" defer></script>
</head>
<body>
    <?php include_once('../blades/sidebar/hk-sidebar.php'); ?>
    
    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <div class="breadcrumbs">
                    <a href="/" class="breadcrumb-item"><i class="fas fa-home"></i></a>
                    <span class="breadcrumb-separator">/</span>
                    <a href="dashboard.php" class="breadcrumb-item">Admin</a>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-current">News Management</span>
                </div>
            </div>
            <?php include_once('../blades/notify/notify.php'); ?>
        </header>

        <div class="content-area-wrapper">
            <div class="hk-hero">
                <div class="banner-content">
                    <div class="hk-icon">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <h1>News Management</h1>
                    <p class="hk-subtitle">Create and manage news articles</p>
                </div>
            </div>

            <div class="news-table-container">
                <div class="news-table-header">
                    <h2><i class="fas fa-list"></i> News Articles</h2>
                    <button class="btn-admin btn-edit" id="add-news-btn">
                        <i class="fas fa-plus"></i> Add New Article
                    </button>
                </div>

                <div class="news-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="news-table-body">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div class="modal-overlay" id="news-modal">
            <div class="modal-container">
                <div class="modal-header">
                    <h3 id="modal-title">Add New Article</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="news-form">
                        <input type="hidden" id="news-id">
                        
                        <div class="form-group">
                            <label for="news-title">Title *</label>
                            <input type="text" id="news-title" class="input-field" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="news-content">Content *</label>
                            <textarea id="news-content" class="textarea-field" rows="10" required></textarea>
                        </div>
                        
                        <div class="modal-actions">
                            <button type="button" class="btn-admin btn-cancel">Cancel</button>
                            <button type="submit" class="btn-admin btn-submit">Save Article</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <footer class="main-footer">
            <p>&copy; <?= date('Y') ?> RLBMODS. All rights reserved.</p>
        </footer>
    </main>
    
    <script src="/js/hk/news.js"></script>
    <script>
        const currentUser = {
            username: '<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>'
        };
    </script>
</body>
</html>
<?php ob_end_flush(); ?>