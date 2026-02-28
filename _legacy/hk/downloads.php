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

// Authentication
requireAuth();
requireStaff();

// Get user info
$userInfo = getUserInfo($_SESSION['user_id']);
if (!$userInfo) {
    header("Location: ../login.php");
    exit();
}

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$start = ($page > 1) ? ($page * $perPage) - $perPage : 0;

// Filters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build SQL query
$where = [];
$params = [];
$types = '';

if ($filter === 'recent') {
    $where[] = "dk.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($filter === 'flagged') {
    $where[] = "(dk.status = 'flagged' OR EXISTS (SELECT 1 FROM download_errors de WHERE de.key_id = dk.id))";
} elseif ($filter === 'banned') {
    $where[] = "dk.status = 'banned'";
}

if (!empty($search)) {
    $where[] = "(u.name LIKE ? OR p.name LIKE ? OR dk.key_value LIKE ? OR dk.ip_address LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
    $types .= 'ssss';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countSql = "SELECT COUNT(dk.id) as total 
             FROM download_keys dk
             JOIN products p ON dk.product_id = p.id
             JOIN usertable u ON dk.user_id = u.id
             $whereClause";

$countStmt = $con->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$pages = ceil($total / $perPage);

// Get download history
$sql = "SELECT dk.*, 
               p.name as product_name, 
               p.version as product_version,
               u.name,
               u.id as user_id
        FROM download_keys dk
        JOIN products p ON dk.product_id = p.id
        JOIN usertable u ON dk.user_id = u.id
        $whereClause
        ORDER BY dk.created_at DESC
        LIMIT ?, ?";

$params = array_merge($params, [$start, $perPage]);
$types .= 'ii';

$stmt = $con->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$downloads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get error counts
$errorCounts = [];
if (!empty($downloads)) {
    $downloadIds = array_column($downloads, 'id');
    $placeholders = implode(',', array_fill(0, count($downloadIds), '?'));
    
    $errorStmt = $con->prepare("
        SELECT key_id, COUNT(*) as error_count 
        FROM download_errors 
        WHERE key_id IN ($placeholders)
        GROUP BY key_id
    ");
    $errorStmt->bind_param(str_repeat('i', count($downloadIds)), ...$downloadIds);
    $errorStmt->execute();
    $errorResults = $errorStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($errorResults as $error) {
        $errorCounts[$error['key_id']] = $error['error_count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RLBMODS • Download History</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/hk/downloads.css">
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
                    <span class="breadcrumb-current">Download History</span>
                </div>
            </div>
            <?php include_once('../blades/notify/notify.php'); ?>
        </header>

        <div class="content-area-wrapper">
            <div class="downloads-hero">
                <div class="hero-content">
                    <div class="hero-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <h1>Download History</h1>
                    <p class="hero-subtitle">Track and manage all file downloads</p>
                </div>
            </div>

            <div class="download-controls">
                <div class="controls-left">
                <div class="filter-options">
                    <button class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>" data-filter="all">All Downloads (<?= $total ?>)</button>
                    <button class="filter-btn <?= $filter === 'recent' ? 'active' : '' ?>" data-filter="recent">Last 7 Days</button>
                    <button class="filter-btn <?= $filter === 'flagged' ? 'active' : '' ?>" data-filter="flagged">Flagged</button>
                    <!-- <button class="filter-btn <?= $filter === 'banned' ? 'active' : '' ?>" data-filter="banned">Banned</button> -->
                </div>
                </div>
                <div class="controls-right">
                    <form method="GET" class="download-search">
                        <input type="hidden" name="filter" value="<?= $filter ?>">
                        <input type="text" name="search" placeholder="Search downloads..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                    <!--<button class="btn-admin btn-export" id="exportBtn">
                        <i class="fas fa-file-export"></i> Export
                    </button> -->
                </div>
            </div>

            <div class="download-history-container">
                <div class="table-responsive">
                    <table class="download-history-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>File</th>
                                <th>Version</th>
                                <th>Download ID</th>
                                <th>IP Address</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Errors</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($downloads)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">No downloads found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($downloads as $download): ?>
                                    <?php
                                    $statusClass = '';
                                    if ($download['status'] === 'banned') {
                                        $statusClass = 'banned';
                                    } elseif (isset($errorCounts[$download['id']]) || $download['status'] === 'flagged') {
                                        $statusClass = 'flagged';
                                    }
                                    ?>
                                    <tr class="<?= $statusClass ?>" data-id="<?= $download['id'] ?>">
                                        <td><?= $download['id'] ?></td>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar <?= $statusClass === 'banned' ? 'banned' : '' ?>">
                                                    <i class="fas fa-<?= $statusClass === 'banned' ? 'user-slash' : 'user' ?>"></i>
                                                </div>
                                                <a class="user-link">
                                                    <?= htmlspecialchars($download['name']) ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($download['product_name']) ?></td>
                                        <td><?= htmlspecialchars($download['product_version']) ?></td>
                                        <td><?= substr($download['key_value'], 0, 6) ?>...<?= substr($download['key_value'], -6) ?></td>
                                        <td><?= $download['ip_address'] ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($download['created_at'])) ?></td>
                                        <td>
                                            <span class="status-badge <?= $statusClass ?>">
                                                <?= ucfirst($statusClass ?: 'valid') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (isset($errorCounts[$download['id']])): ?>
                                                <span class="error-count" title="<?= $errorCounts[$download['id']] ?> errors">
                                                    <?= $errorCounts[$download['id']] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="no-errors">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn-admin btn-view" title="View Details" data-id="<?= $download['id'] ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($statusClass === 'banned'): ?>
                                                <button class="btn-admin btn-unban" title="Unban Download" data-id="<?= $download['id'] ?>">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            <?php else: ?>
                                               <!-- <button class="btn-admin btn-ban" title="Ban Download" data-id="<?= $download['id'] ?>">
                                                    <i class="fas fa-ban"></i>
                                                </button> -->
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>" class="pagination-btn">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn disabled">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <a href="?page=<?= $i ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>" class="pagination-btn <?= $page == $i ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $pages): ?>
                        <a href="?page=<?= $page+1 ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>" class="pagination-btn">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn disabled">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
            <!-- ========== Footer Start ========== -->
    <?php include_once('../blades/footer/footer.php'); ?>
    <!-- ========== Footer Ends ========== -->
    </main>

    <!-- Download Details Modal -->
    <div class="modal-overlay" id="downloadDetailsModal" style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Download Details</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="download-details">
                    <div class="detail-row">
                        <span class="detail-label">Download ID:</span>
                        <span class="detail-value" id="detail-id"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">User:</span>
                        <span class="detail-value" id="detail-user"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">File:</span>
                        <span class="detail-value" id="detail-file"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Version:</span>
                        <span class="detail-value" id="detail-version"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">IP Address:</span>
                        <span class="detail-value" id="detail-ip"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">User Agent:</span>
                        <span class="detail-value" id="detail-agent"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date:</span>
                        <span class="detail-value" id="detail-date"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value" id="detail-status"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Download Count:</span>
                        <span class="detail-value" id="detail-count"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Errors:</span>
                        <span class="detail-value" id="detail-errors"></span>
                    </div>
                    <div class="error-details" id="errorDetails" style="display: none;">
                        <h4>Error Reasons</h4>
                        <div id="errorList"></div>
                    </div>
                </div>

                <div class="modal-actions">
                    <!-- <button type="button" class="btn-admin btn-ban" id="banFromDetailsBtn">
                        <i class="fas fa-ban"></i> Ban This Download
                    </button> -->
                </div>
            </div>
        </div>
    </div>

    <!-- Ban Download Modal -->
    <div class="modal-overlay" id="banDownloadModal" style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-ban"></i> Ban Download</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="banDownloadForm">
                    <div class="form-group">
                        <label for="banDownloadId">Download ID</label>
                        <input type="text" id="banDownloadId" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="banDownloadUser">User</label>
                        <input type="text" id="banDownloadUser" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="banDownloadFile">File</label>
                        <input type="text" id="banDownloadFile" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="banDownloadReason">Reason</label>
                        <input type="text" id="banDownloadReason" required placeholder="Enter reason...">
                    </div>
                    
                    <div class="form-group">
                        <label for="banDownloadAction">Additional Action</label>
                        <select id="banDownloadAction">
                            <option value="none">Just ban this download</option>
                            <option value="ban_user">Ban user from downloading</option>
                            <option value="ban_ip">Ban IP address</option>
                            <option value="ban_both">Ban user and IP address</option>
                        </select>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-admin btn-cancel">Cancel</button>
                        <button type="submit" class="btn-admin btn-submit">Confirm Ban</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal-overlay" id="exportModal" style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-file-export"></i> Export Download History</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="exportForm" action="/hk/api/export_downloads.php" method="POST">
                    <div class="form-group">
                        <label for="exportFormat">Format</label>
                        <select id="exportFormat" name="format">
                            <option value="csv">CSV</option>
                            <option value="json">JSON</option>
                            <option value="excel">Excel</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="exportRange">Date Range</label>
                        <select id="exportRange" name="range">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="customDateRange" style="display: none;">
                        <div class="date-range">
                            <input type="date" id="exportStartDate" name="start_date">
                            <span>to</span>
                            <input type="date" id="exportEndDate" name="end_date">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="exportFilter">Filter</label>
                        <select id="exportFilter" name="filter">
                            <option value="all">All Downloads</option>
                            <option value="valid">Valid Only</option>
                            <option value="flagged">Flagged Only</option>
                            <option value="banned">Banned Only</option>
                        </select>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-admin btn-cancel">Cancel</button>
                        <button type="submit" class="btn-admin btn-submit">Export</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="/js/hk/download-history.js"></script>
</body>
</html>