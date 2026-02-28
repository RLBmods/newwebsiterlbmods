<?php
// Enable output buffering
ob_start();

// Enable error reporting
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

if ($filter === 'completed') {
    $where[] = "status = 'completed'";
} elseif ($filter === 'pending') {
    $where[] = "status = 'pending'";
} elseif ($filter === 'failed') {
    $where[] = "status = 'failed'";
} elseif ($filter === 'refunded') {
    $where[] = "status = 'refunded'";
}

if (!empty($search)) {
    $where[] = "(pt.order_id LIKE ? OR pt.transaction_id LIKE ? OR u.name LIKE ? OR pt.payment_method LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
    $types .= 'ssss';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countSql = "SELECT COUNT(pt.id) as total 
             FROM payment_transactions pt
             LEFT JOIN usertable u ON pt.user_id = u.id
             $whereClause";
$countStmt = $con->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$pages = ceil($total / $perPage);

// Get transactions with user information
$sql = "SELECT pt.*, u.name 
        FROM payment_transactions pt
        LEFT JOIN usertable u ON pt.user_id = u.id
        $whereClause 
        ORDER BY pt.created_at DESC 
        LIMIT ?, ?";
$params = array_merge($params, [$start, $perPage]);
$types .= 'ii';

$stmt = $con->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get counts for each filter
$counts = [
    'all' => $con->query("SELECT COUNT(*) FROM payment_transactions")->fetch_row()[0],
    'completed' => $con->query("SELECT COUNT(*) FROM payment_transactions WHERE status = 'completed'")->fetch_row()[0],
    'pending' => $con->query("SELECT COUNT(*) FROM payment_transactions WHERE status = 'pending'")->fetch_row()[0],
    'failed' => $con->query("SELECT COUNT(*) FROM payment_transactions WHERE status = 'failed'")->fetch_row()[0],
    'refunded' => $con->query("SELECT COUNT(*) FROM payment_transactions WHERE status = 'refunded'")->fetch_row()[0]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RLBMODS • Transactions</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/hk/dashboard.css">
    <link rel="stylesheet" href="/css/hk/transactions.css">
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
                    <span class="breadcrumb-current">Transactions</span>
                </div>
            </div>
            <?php include_once('../blades/notify/notify.php'); ?>
        </header>

        <div class="content-area-wrapper">
            <div class="transactions-hero">
                <div class="hero-content">
                    <div class="hero-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <h1>Transaction History</h1>
                    <p class="hero-subtitle">View and manage all payment transactions</p>
                </div>
            </div>

            <div class="transaction-controls">
                <div class="controls-left">
                    <div class="filter-options">
                        <button class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>" data-filter="all">All (<?= $counts['all'] ?>)</button>
                        <button class="filter-btn <?= $filter === 'completed' ? 'active' : '' ?>" data-filter="completed">Completed (<?= $counts['completed'] ?>)</button>
                        <button class="filter-btn <?= $filter === 'pending' ? 'active' : '' ?>" data-filter="pending">Pending (<?= $counts['pending'] ?>)</button>
                        <button class="filter-btn <?= $filter === 'failed' ? 'active' : '' ?>" data-filter="failed">Failed (<?= $counts['failed'] ?>)</button>
                        <button class="filter-btn <?= $filter === 'refunded' ? 'active' : '' ?>" data-filter="refunded">Refunded (<?= $counts['refunded'] ?>)</button>
                    </div>
                </div>
                <div class="controls-right">
                    <div class="transaction-search">
                        <input type="text" placeholder="Search transactions..." id="transactionSearch" value="<?= htmlspecialchars($search) ?>">
                        <button id="searchBtn"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </div>

            <div class="transaction-history-container">
                <div class="table-responsive">
                    <table class="transaction-history-table">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>User</th>
                                <th>Order ID</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No transactions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $txn): ?>
                                    <tr class="txn-<?= $txn['status'] ?>">
                                        <td><?= htmlspecialchars($txn['transaction_id'] ?: $txn['order_id']) ?></td>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar <?= $txn['status'] === 'failed' ? 'banned' : '' ?>">
                                                    <i class="fas <?= $txn['status'] === 'failed' ? 'fa-user-slash' : 'fa-user' ?>"></i>
                                                </div>
                                                <span><?= htmlspecialchars($txn['name'] ?: $txn['user_id']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($txn['order_id']) ?></td>
                                        <td class="amount-<?= $txn['status'] === 'refunded' ? 'negative' : 'positive' ?>">
                                            $<?= number_format($txn['amount'], 2) ?>
                                        </td>
                                        <td><?= htmlspecialchars(ucfirst($txn['payment_method'])) ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($txn['created_at'])) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $txn['status'] ?>">
                                                <?= ucfirst($txn['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn-admin btn-view" title="View Details" data-id="<?= $txn['id'] ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
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
            
            <!-- Transaction Details Modal -->
            <div class="modal-overlay" id="transactionDetailsModal" style="display: none;">
                <div class="modal-container">
                    <div class="modal-header">
                        <h3><i class="fas fa-info-circle"></i> Transaction Details</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="transaction-details">
                            <div class="detail-row">
                                <span class="detail-label">Transaction ID:</span>
                                <span class="detail-value" id="detail-txn-id"></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Order ID:</span>
                                <span class="detail-value" id="detail-order-id"></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">User ID:</span>
                                <span class="detail-value" id="detail-txn-user"></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Amount:</span>
                                <span class="detail-value" id="detail-txn-amount"></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Quantity:</span>
                                <span class="detail-value" id="detail-txn-quantity"></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Payment Method:</span>
                                <span class="detail-value" id="detail-txn-method"></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Gateway:</span>
                                <span class="detail-value" id="detail-txn-gateway"></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Date:</span>
                                <span class="detail-value" id="detail-txn-date"></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Status:</span>
                                <span class="detail-value" id="detail-txn-status"></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Delivery Status:</span>
                                <span class="detail-value" id="detail-delivery-status"></span>
                            </div>
                            <div class="detail-row full-width">
                                <span class="detail-label">Delivered Item:</span>
                                <pre class="detail-value" id="detail-delivered-item"></pre>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Note:</span>
                                <span class="detail-value" id="detail-txn-note"></span>
                            </div>
                        </div>

                        <div class="modal-actions">
                            <button class="btn-admin btn-cancel">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
            <!-- ========== Footer Start ========== -->
    <?php include_once('../blades/footer/footer.php'); ?>
    <!-- ========== Footer Ends ========== -->
    </main>

    <script src="/js/hk/transactions.js"></script>
</body>
</html>