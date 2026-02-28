<?php
// Enable output buffering
ob_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once '../includes/session.php'; // This handles session_start()
require_once '../includes/functions.php';
require_once '../db/connection.php';
require_once '../includes/logging.php';
require_once '../includes/get_user_info.php';

// Ensure the user is authenticated
requireAuth();

// Ensure the user is allowed accessing the page
requireStaff();

// Fetch real statistics from database
try {
    // Total Users
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM usertable");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];
    
    // New Users Today
    $stmt = $pdo->query("SELECT COUNT(*) as new_today FROM usertable WHERE DATE(created_at) = CURDATE()");
    $newToday = $stmt->fetch(PDO::FETCH_ASSOC)['new_today'];
    
    // Active Subscriptions
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as active_subscriptions FROM shop_purchases WHERE status = 'active' AND (expires_at IS NULL OR expires_at > NOW())");
    $activeSubscriptions = $stmt->fetch(PDO::FETCH_ASSOC)['active_subscriptions'];
    
    // Renewals Today
    $stmt = $pdo->query("SELECT COUNT(*) as renewals_today FROM shop_purchases WHERE DATE(purchase_date) = CURDATE() AND status = 'active'");
    $renewalsToday = $stmt->fetch(PDO::FETCH_ASSOC)['renewals_today'];
    
    // Today's Revenue
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as today_revenue FROM transaction_history WHERE DATE(created_at) = CURDATE() AND status = 'completed'");
    $todayRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['today_revenue'];
    
    // Revenue Change from Yesterday
    $stmt = $pdo->prepare("
        SELECT 
            ROUND(
                ((SELECT COALESCE(SUM(amount), 0) 
                  FROM transaction_history 
                  WHERE DATE(created_at) = CURDATE() 
                  AND status = 'completed') - 
                 (SELECT COALESCE(SUM(amount), 0) 
                  FROM transaction_history 
                  WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) 
                  AND status = 'completed')
                ) / 
                NULLIF((SELECT COALESCE(SUM(amount), 1) 
                        FROM transaction_history 
                        WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) 
                        AND status = 'completed'), 0) * 100,
            2) as revenue_change_percent
    ");
    $stmt->execute();
    $revenueChange = $stmt->fetch(PDO::FETCH_ASSOC)['revenue_change_percent'];
    $revenueChangeClass = $revenueChange >= 0 ? 'positive' : 'negative';
    $revenueChangeIcon = $revenueChange >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
    
    // Banned Users
    $stmt = $pdo->query("SELECT COUNT(*) as banned_users FROM usertable WHERE banned = 1");
    $bannedUsers = $stmt->fetch(PDO::FETCH_ASSOC)['banned_users'];
    
    // Banned Today
    $stmt = $pdo->query("SELECT COUNT(*) as banned_today FROM bans WHERE DATE(banned_at) = CURDATE()");
    $bannedToday = $stmt->fetch(PDO::FETCH_ASSOC)['banned_today'];
    
    // Recent Subscriptions
    $stmt = $pdo->query("
        SELECT sp.*, u.name as username, p.name as product_name 
        FROM shop_purchases sp 
        JOIN usertable u ON sp.user_id = u.id 
        JOIN products p ON sp.product_id = p.id 
        WHERE sp.status = 'active' 
        ORDER BY sp.purchase_date DESC 
        LIMIT 5
    ");
    $recentSubscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent Activity
    $stmt = $pdo->query("
        SELECT l.*, u.name as username 
        FROM logs l 
        LEFT JOIN usertable u ON l.user_id = u.id 
        ORDER BY l.timestamp DESC 
        LIMIT 5
    ");
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Log error but don't display to user
    error_log("Dashboard statistics error: " . $e->getMessage());
    
    // Set default values if query fails
    $totalUsers = 0;
    $newToday = 0;
    $activeSubscriptions = 0;
    $renewalsToday = 0;
    $todayRevenue = 0;
    $revenueChange = 0;
    $revenueChangeClass = 'positive';
    $revenueChangeIcon = 'fa-arrow-up';
    $bannedUsers = 0;
    $bannedToday = 0;
    $recentSubscriptions = [];
    $recentActivity = [];
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name;?> • Dashboard</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/hk/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.11.2/css/all.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/heartbeat.js" defer></script>
    <script src="../js/notify.js" defer></script>
</head>
<body>
<div class="sidebar-overlay"></div>
    <!-- ========== Left Sidebar Start ========== -->
    <?php include_once('../blades/sidebar/hk-sidebar.php'); ?>
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
        <?php include_once('../blades/notify/notify.php'); ?>
        <!-- ========== Left Sidebar Ends ========== -->

        </header>

        <div class="content-area-wrapper">

            <!-- Admin Stats -->
            <div class="admin-stats-grid">
                <div class="admin-stat-card">
                    <h3><i class="fas fa-users"></i> Total Users</h3>
                    <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> <?php echo $newToday; ?> new today
                    </div>
                </div>
                <div class="admin-stat-card">
                    <h3><i class="fas fa-crown"></i> Active Subscriptions</h3>
                    <div class="stat-value"><?php echo number_format($activeSubscriptions); ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> <?php echo $renewalsToday; ?> renewals today
                    </div>
                </div>
                <div class="admin-stat-card">
                    <h3><i class="fas fa-dollar-sign"></i> Today's Revenue</h3>
                    <div class="stat-value">$<?php echo number_format($todayRevenue, 2); ?></div>
                    <div class="stat-change <?php echo $revenueChangeClass; ?>">
                        <i class="fas <?php echo $revenueChangeIcon; ?>"></i> <?php echo abs($revenueChange); ?>% from yesterday
                    </div>
                </div>
                <div class="admin-stat-card">
                    <h3><i class="fas fa-ban"></i> Banned Users</h3>
                    <div class="stat-value"><?php echo number_format($bannedUsers); ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> <?php echo $bannedToday; ?> today
                    </div>
                </div>
            </div>

            <!-- Recent Subscriptions -->
            <div class="admin-table-container">
                <div class="admin-table-header">
                    <h2><i class="fas fa-crown"></i> Recent Purchases</h2>
                    <!-- <button class="btn-admin btn-edit">View All</button> -->
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentSubscriptions)): ?>
                            <?php foreach ($recentSubscriptions as $subscription): ?>
                                <tr>
                                    <td>
                                        <span class="user-avatar"><i class="fas fa-user"></i></span>
                                        <?php echo htmlspecialchars($subscription['username']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($subscription['product_name']); ?></td>
                                    <td><span class="status-badge status-active">Active</span></td>
                                    <td><?php echo date('Y-m-d', strtotime($subscription['expires_at'])); ?></td>
                                    <td>
                                        <div class="admin-actions">
                                            <button class="btn-admin btn-edit"><i class="fas fa-edit"></i></button>
                                            <button class="btn-admin btn-ban"><i class="fas fa-ban"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No recent subscriptions</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Activity -->
            <div class="admin-table-container">
                <div class="admin-table-header">
                    <h2><i class="fas fa-history"></i> Recent Activity</h2>
                    <!-- <button class="btn-admin btn-edit">View Logs</button> -->
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Date</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentActivity)): ?>
                            <?php foreach ($recentActivity as $activity): ?>
                                <tr>
                                    <td>
                                        <span class="user-avatar"><i class="fas fa-user"></i></span>
                                        <?php echo htmlspecialchars($activity['username'] ?: 'System'); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($activity['action_type']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($activity['details'], 0, 50) . (strlen($activity['details']) > 50 ? '...' : '')); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($activity['timestamp'])); ?></td>
                                    <td><?php echo htmlspecialchars($activity['ip_address']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No recent activity</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    <!-- ========== Footer Start ========== -->
    <?php include_once('../blades/footer/footer.php'); ?>
    <!-- ========== Footer Ends ========== -->
    </main>
    <script src="../js/hk/dashboard.js"></script>
</body>
</html>