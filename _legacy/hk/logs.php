<?php
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../db/connection.php';
require_once '../includes/logging.php';
require_once '../includes/get_user_info.php';

requireAuth();
requireStaff();

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$start = ($page > 1) ? ($page - 1) * $perPage : 0;  // Fixed pagination calculation

// Filters
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$range = $_GET['range'] ?? 'all';

// Build SQL query
$where = [];
$params = [];
$types = '';

// Add filter conditions based on ACTION
if ($filter !== 'all') {
    switch ($filter) {
        case 'login':
            $where[] = "action LIKE ?";
            $params[] = "%Successful login%";
            $types .= 's';
            break;
        case 'failed_login':
            $where[] = "action LIKE ?";
            $params[] = "%Failed login%";
            $types .= 's';
            break;
        case 'admin':
            $where[] = "action_type = ?";
            $params[] = "admin";
            $types .= 's';
            break;
        case 'security':
            $where[] = "(action LIKE ? OR action_type = ?)";
            array_push($params, "%CSRF%", "security");
            $types .= 'ss';
            break;
    }
}

// Add search condition
if (!empty($search)) {
    $where[] = "(username LIKE ? OR ip_address LIKE ? OR action LIKE ? OR details LIKE ?)";
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
    $types .= 'ssss';
}

// Add time range condition
if ($range !== 'all') {
    switch ($range) {
        case 'today':
            $where[] = "DATE(timestamp) = CURDATE()";
            break;
        case 'week':
            $where[] = "timestamp >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where[] = "timestamp >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countSql = "SELECT COUNT(id) as total FROM logs $whereClause";
$countStmt = $con->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$pages = ceil($total / $perPage);

// Get logs - handle LIMIT parameters separately
$sql = "SELECT * FROM logs $whereClause ORDER BY timestamp DESC LIMIT ?, ?";
$limitParams = array_merge($params, [$start, $perPage]);
$limitTypes = $types . 'ii';

$stmt = $con->prepare($sql);
$stmt->bind_param($limitTypes, ...$limitParams);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get counts for each filter - include time range if set
$countConditions = [
    'all' => "1=1",
    'login' => "action LIKE '%Successful login%'",
    'failed_login' => "action LIKE '%Failed login%'",
    'admin' => "action_type = 'admin'",
    'security' => "(action LIKE '%CSRF%' OR action_type = 'security')"
];

$rangeCondition = "";
if ($range !== 'all') {
    switch ($range) {
        case 'today':
            $rangeCondition = " AND DATE(timestamp) = CURDATE()";
            break;
        case 'week':
            $rangeCondition = " AND timestamp >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $rangeCondition = " AND timestamp >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }
}

$counts = [];
foreach ($countConditions as $key => $condition) {
    $sql = "SELECT COUNT(*) FROM logs WHERE $condition";
    if ($range !== 'all') {
        switch ($range) {
            case 'today':
                $sql .= " AND DATE(timestamp) = CURDATE()";
                break;
            case 'week':
                $sql .= " AND timestamp >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $sql .= " AND timestamp >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
    }
    $result = $con->query($sql);
    $counts[$key] = $result->fetch_row()[0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RLBMODS • Logs</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/hk/dashboard.css">
    <link rel="stylesheet" href="/css/hk/logs.css">
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
                    <span class="breadcrumb-current">Logs</span>
                </div>
            </div>
            <?php include_once('../blades/notify/notify.php'); ?>
        </header>

        <div class="content-area-wrapper">
            <div class="hk-hero">
                <div class="banner-content">
                    <div class="hk-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h1>Logs</h1>
                    <p class="hk-subtitle">Track all system and user activities</p>
                </div>
            </div>

            <div class="table-controls">
                <div class="controls-left">
                <div class="filter-options">
                    <button class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>" data-filter="all">All Logs (<?= $counts['all'] ?>)</button>
                    <button class="filter-btn <?= $filter === 'login' ? 'active' : '' ?>" data-filter="login">Logins (<?= $counts['login'] ?>)</button>
                    <button class="filter-btn <?= $filter === 'failed_login' ? 'active' : '' ?>" data-filter="failed_login">Failed Logins (<?= $counts['failed_login'] ?>)</button>
                    <button class="filter-btn <?= $filter === 'admin' ? 'active' : '' ?>" data-filter="admin">Admin Actions (<?= $counts['admin'] ?>)</button>
                    <button class="filter-btn <?= $filter === 'security' ? 'active' : '' ?>" data-filter="security">Security (<?= $counts['security'] ?>)</button>
                </div>
                    <select id="logTimeRange" class="filter-select">
                        <option value="all" <?= $range === 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="today" <?= $range === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="week" <?= $range === 'week' ? 'selected' : '' ?>>This Week</option>
                        <option value="month" <?= $range === 'month' ? 'selected' : '' ?>>This Month</option>
                    </select>
                </div>
                <div class="controls-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search logs..." id="logSearch" value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
            </div>

            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>IP Address</th>
                            <th>Action Type</th>
                            <th>Action</th>
                            <th>Status</th>
                            <th>Details</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No logs found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="log-entry log-<?= htmlspecialchars($log['action_type']) ?>">
                                    <td><?= date('Y-m-d H:i:s', strtotime($log['timestamp'])) ?></td>
                                    <td>
                                        <span class="user-avatar">
                                            <i class="fas fa-<?= 
                                                $log['action_type'] === 'admin' ? 'user-shield' : 
                                                ($log['action_type'] === 'security' ? 'shield-alt' : 'user') 
                                            ?>"></i>
                                        </span>
                                        <?= htmlspecialchars($log['username']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($log['ip_address']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= htmlspecialchars($log['status']) ?>">
                                            <?= ucfirst(htmlspecialchars($log['action_type'])) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($log['action']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= htmlspecialchars($log['status']) ?>">
                                            <?= ucfirst(htmlspecialchars($log['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(substr($log['details'], 0, 50)) ?><?= strlen($log['details']) > 50 ? '...' : '' ?></td>
                                    <td>
                                        <div class="admin-actions">
                                            <button class="btn-admin btn-view" title="View Details" data-id="<?= $log['id'] ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&range=<?= $range ?>" class="pagination-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="pagination-btn disabled">
                        <i class="fas fa-chevron-left"></i>
                    </span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <a href="?page=<?= $i ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&range=<?= $range ?>" class="pagination-btn <?= $page == $i ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $pages): ?>
                    <a href="?page=<?= $page+1 ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&range=<?= $range ?>" class="pagination-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="pagination-btn disabled">
                        <i class="fas fa-chevron-right"></i>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Log Details Modal -->
            <div class="modal-overlay" id="logDetailsModal" style="display: none;">
                <div class="modal-container">
                    <div class="modal-header">
                        <h3><i class="fas fa-info-circle"></i> Log Entry Details</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="log-detail-row">
                            <span class="log-detail-label">Timestamp:</span>
                            <span class="log-detail-value" id="detailTimestamp"></span>
                        </div>
                        <div class="log-detail-row">
                            <span class="log-detail-label">User:</span>
                            <span class="log-detail-value" id="detailUser"></span>
                        </div>
                        <div class="log-detail-row">
                            <span class="log-detail-label">IP Address:</span>
                            <span class="log-detail-value" id="detailIp"></span>
                        </div>
                        <div class="log-detail-row">
                            <span class="log-detail-label">Action Type:</span>
                            <span class="log-detail-value" id="detailActionType"></span>
                        </div>
                        <div class="log-detail-row">
                            <span class="log-detail-label">Action:</span>
                            <span class="log-detail-value" id="detailAction"></span>
                        </div>
                        <div class="log-detail-row">
                            <span class="log-detail-label">Status:</span>
                            <span class="log-detail-value" id="detailStatus"></span>
                        </div>
                        <div class="log-detail-row full-width">
                            <span class="log-detail-label">Details:</span>
                            <div class="log-detail-value" id="detailDetails"></div>
                        </div>
                        <div class="log-detail-row full-width">
                            <span class="log-detail-label">Additional Data:</span>
                            <pre class="log-detail-value" id="detailAdditional"></pre>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn-admin btn-cancel">Close</button>
                    </div>
                </div>
            </div>
        </div>
            <!-- ========== Footer Start ========== -->
    <?php include_once('../blades/footer/footer.php'); ?>
    <!-- ========== Footer Ends ========== -->
    </main>

    <script src="/js/hk/activity-logs.js"></script>
</body>
</html>