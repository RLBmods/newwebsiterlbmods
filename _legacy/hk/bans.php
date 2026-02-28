<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../db/connection.php';
require_once '../includes/logging.php';
require_once '../includes/get_user_info.php';

requireAuth();
requireStaff();

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
    <title>RLBMODS • Bans Management</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/hk/dashboard.css">
    <link rel="stylesheet" href="/css/hk/bans.css">
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
                    <span class="breadcrumb-current">Bans Management</span>
                </div>
            </div>
            <?php include_once('../blades/notify/notify.php'); ?>
        </header>

        <div class="content-area-wrapper">
            <div class="hk-hero">
                <div class="banner-content">
                    <div class="hk-icon">
                        <i class="fas fa-ban"></i>
                    </div>
                    <h1>Bans Management</h1>
                    <p class="hk-subtitle">Manage and extend user punishments</p>
                </div>
            </div>

            <div class="table-controls">
                <div class="controls-left">
                <div class="filter-options">
                    <button class="filter-btn active" data-filter="all">All Bans</button>
                    <button class="filter-btn" data-filter="active">Active</button>
                    <button class="filter-btn" data-filter="expired">Expired</button>
                    <button class="filter-btn" data-filter="permanent">Permanent</button>
                    <button class="filter-btn" data-filter="unbanned">Unbanned</button>
                </div>
                </div>
                <div class="controls-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search bans..." id="banSearch">
                    </div>
                    <button class="btn-admin btn-ban" id="addBanBtn">
                        <i class="fas fa-user-slash"></i> New Ban
                    </button>
                </div>
            </div>

            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Reason</th>
                            <th>Banned By</th>
                            <th>Banned On</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bans-table-body">
                        <!-- Bans will be loaded via JavaScript -->
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <button id="prevPage" class="pagination-btn disabled"><i class="fas fa-chevron-left"></i></button>
                <div id="pageNumbers" class="pagination-numbers"></div>
                <button id="nextPage" class="pagination-btn"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        
        <!-- New Ban Modal -->
        <div class="modal-overlay" id="newBanModal">
            <div class="modal-container">
                <div class="modal-header">
                    <h3><i class="fas fa-user-slash"></i> New Ban</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="newBanForm">
                        <div class="form-group">
                            <label for="banUsername">Username *</label>
                            <input type="text" id="banUsername" class="input-field" required>
                        </div>
                        <div class="form-group">
                            <label for="banReason">Reason *</label>
                            <input type="text" id="banReason" class="input-field" required>
                        </div>
                        <div class="form-group">
                            <label for="banDuration">Duration *</label>
                            <select id="banDuration" class="select-field">
                                <option value="permanent">Permanent</option>
                                <option value="1d">1 Day</option>
                                <option value="7d">7 Days</option>
                                <option value="30d">30 Days</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div class="form-group" id="customBanDateGroup" style="display: none;">
                            <label for="customBanDate">Custom End Date *</label>
                            <input type="datetime-local" id="customBanDate" class="input-field">
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn-admin btn-cancel">Cancel</button>
                            <button type="submit" class="btn-admin btn-submit">Confirm Ban</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Extend Ban Modal -->
        <div class="modal-overlay" id="extendBanModal">
            <div class="modal-container">
                <div class="modal-header">
                    <h3><i class="fas fa-clock"></i> Extend Ban</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="extendBanForm">
                        <input type="hidden" id="extendBanId">
                        <div class="form-group">
                            <label>Username</label>
                            <div class="input-field" id="extendUsernameDisplay"></div>
                        </div>
                        <div class="form-group">
                            <label for="extendDuration">Duration *</label>
                            <select id="extendDuration" class="select-field">
                                <option value="1d">1 Day</option>
                                <option value="7d">7 Days</option>
                                <option value="30d">30 Days</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div class="form-group" id="customExtendDateGroup" style="display: none;">
                            <label for="customExtendDate">Custom End Date *</label>
                            <input type="datetime-local" id="customExtendDate" class="input-field">
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn-admin btn-cancel">Cancel</button>
                            <button type="submit" class="btn-admin btn-submit">Extend Ban</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Unban Modal -->
        <div class="modal-overlay" id="unbanModal">
            <div class="modal-container">
                <div class="modal-header">
                    <h3><i class="fas fa-unlock"></i> Unban User</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="unbanId">
                    <p id="unbanMessage">Are you sure you want to unban this user?</p>
                    <div class="modal-actions">
                        <button type="button" class="btn-admin btn-cancel">Cancel</button>
                        <button type="button" id="confirmUnbanBtn" class="btn-admin btn-submit">Confirm Unban</button>
                    </div>
                </div>
            </div>
        </div>
            <!-- ========== Footer Start ========== -->
    <?php include_once('../blades/footer/footer.php'); ?>
    <!-- ========== Footer Ends ========== -->
    </main>
    
    <script src="/js/hk/bans-management.js"></script>
    <script>
        const currentUser = {
            id: <?= $_SESSION['user_id'] ?>,
            username: '<?= htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') ?>'
        };
    </script>
</body>
</html>
<?php ob_end_flush(); ?>