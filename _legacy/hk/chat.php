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
$perPage = 20;
$start = ($page > 1) ? ($page * $perPage) - $perPage : 0;

// Filters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build SQL query
$where = [];
$params = [];
$types = '';

if ($filter === 'flagged') {
    $where[] = "m.flagged = 1";
} elseif ($filter === 'deleted') {
    $where[] = "m.deleted = 1";
}

if (!empty($search)) {
    $where[] = "(m.message LIKE ? OR u.name LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%"]);
    $types .= 'ss';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countSql = "SELECT COUNT(m.id) as total 
             FROM messages m
             LEFT JOIN usertable u ON m.username = u.name
             $whereClause";
$countStmt = $con->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$pages = ceil($total / $perPage);

// Get messages with user information
$sql = "SELECT m.*, u.name as username, u.role, u.profile_picture, u.banned, u.muted
        FROM messages m
        LEFT JOIN usertable u ON m.username = u.name
        $whereClause 
        ORDER BY m.timestamp DESC 
        LIMIT ?, ?";
$params = array_merge($params, [$start, $perPage]);
$types .= 'ii';

$stmt = $con->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get counts for each filter
$counts = [
    'all' => $con->query("SELECT COUNT(*) FROM messages")->fetch_row()[0],
    'flagged' => $con->query("SELECT COUNT(*) FROM messages WHERE flagged = 1")->fetch_row()[0],
    'deleted' => $con->query("SELECT COUNT(*) FROM messages WHERE deleted = 1")->fetch_row()[0]
];

// Get banned words
$bannedWords = $con->query("SELECT word FROM banned_words")->fetch_all(MYSQLI_ASSOC);
$bannedWords = array_column($bannedWords, 'word');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RLBMODS • Chat Management</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/hk/chat.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                    <span class="breadcrumb-current">Chat Management</span>
                </div>
            </div>
            <?php include_once('../blades/notify/notify.php'); ?>
        </header>

        <div class="content-area-wrapper">
            <!-- Chat Management Header -->
            <div class="chat-hero">
                <div class="hero-content">
                    <div class="hero-icon">
                        <i class="fas fa-comment-dots"></i>
                    </div>
                    <h1>Chat Management</h1>
                    <p class="hero-subtitle">Monitor and moderate community chat</p>
                </div>
            </div>

            <!-- Chat Controls -->
            <div class="chat-controls">
                <div class="controls-left">
                    <div class="filter-options">
                        <button class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>" data-filter="all">All Messages (<?= $counts['all'] ?>)</button>
                        <button class="filter-btn <?= $filter === 'flagged' ? 'active' : '' ?>" data-filter="flagged">Flagged (<?= $counts['flagged'] ?>)</button>
                        <button class="filter-btn <?= $filter === 'deleted' ? 'active' : '' ?>" data-filter="deleted">Deleted (<?= $counts['deleted'] ?>)</button>
                    </div>
                </div>
                <div class="controls-right">
                    <button class="btn-admin btn-settings" id="chatSettingsBtn">
                        <i class="fas fa-cog"></i> Chat Settings
                    </button>
                    <button class="btn-admin btn-ban" id="bannedWordsBtn">
                        <i class="fas fa-ban"></i> Banned Words
                    </button>
                </div>
            </div>

            <!-- Two Column Layout -->
            <div class="chat-columns">
                <!-- Left Column - Chat Messages -->
                <div class="messages-column">
                    <div class="chat-messages-container">
                        <div class="chat-messages-header">
                            <h3><i class="fas fa-comments"></i> Chat History</h3>
                            <div class="message-search">
                                <input type="text" placeholder="Search messages..." id="messageSearch" value="<?= htmlspecialchars($search) ?>">
                                <button><i class="fas fa-search"></i></button>
                            </div>
                        </div>
                        <div class="chat-messages">
                            <?php if (empty($messages)): ?>
                                <div class="no-messages">No messages found</div>
                            <?php else: ?>
                                <?php foreach ($messages as $message): ?>
                                    <div class="message <?= $message['deleted'] ? 'deleted' : '' ?> <?= $message['flagged'] ? 'flagged' : '' ?>" data-id="<?= $message['id'] ?>">
                                        <div class="message-header">
                                            <span class="user-avatar <?= $message['banned'] ? 'banned' : '' ?>">
                                                <img src="<?= htmlspecialchars($message['profile_picture']) ?>" alt="<?= htmlspecialchars($message['username']) ?>">
                                            </span>
                                            <span class="username"><?= htmlspecialchars($message['username']) ?></span>
                                            <span class="message-time"><?= date('M j, Y g:i A', strtotime($message['timestamp'])) ?></span>
                                            <?php if ($message['deleted']): ?>
                                                <span class="deleted-badge">Deleted</span>
                                            <?php endif; ?>
                                            <?php if ($message['flagged']): ?>
                                                <span class="flagged-badge">Flagged</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-content">
                                            <?= $message['deleted'] ? '[This message has been removed by moderator]' : nl2br(htmlspecialchars($message['message'])) ?>
                                        </div>
                                        <div class="message-actions">
                                            <?php if (!$message['deleted']): ?>
                                                <button class="btn-admin btn-delete" title="Delete Message" data-id="<?= $message['id'] ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <button class="btn-admin btn-ban" title="Ban User" data-username="<?= htmlspecialchars($message['username']) ?>">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                                <button class="btn-admin btn-flag" title="<?= $message['flagged'] ? 'Unflag Message' : 'Flag Message' ?>" data-id="<?= $message['id'] ?>" data-flagged="<?= $message['flagged'] ? '1' : '0' ?>">
                                                    <i class="fas fa-flag"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-admin btn-restore" title="Restore Message" data-id="<?= $message['id'] ?>">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - User Info and Actions -->
                <div class="actions-column">
                    <div class="user-info-card">
                        <div class="user-header">
                            <div class="user-avatar-large">
                                <i class="fas fa-user" id="selectedUserAvatar"></i>
                            </div>
                            <div class="user-details">
                                <h3 class="username" id="selectedUsername">Selected User</h3>
                                <span class="user-status" id="selectedUserStatus">Click a message to view user</span>
                            </div>
                        </div>
                        <div class="user-stats">
                            <div class="stat-item">
                                <span class="stat-label">Messages</span>
                                <span class="stat-value" id="userMessageCount">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Warnings</span>
                                <span class="stat-value" id="userWarningCount">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Bans</span>
                                <span class="stat-value" id="userBanCount">0</span>
                            </div>
                        </div>
                        <div class="user-actions">
                            <button class="btn-admin btn-warn" id="warnUserBtn">
                                <i class="fas fa-exclamation-triangle"></i> Warn User
                            </button>
                            <button class="btn-admin btn-timeout" id="timeoutUserBtn">
                                <i class="fas fa-clock"></i> Timeout (5m)
                            </button>
                            <button class="btn-admin btn-ban" id="banUserBtn">
                                <i class="fas fa-ban"></i> Ban User
                            </button>
                        </div>
                    </div>
                    
                    <div class="quick-actions-card">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                        <div class="quick-actions">
                            <button class="btn-admin btn-clear" id="clearChatBtn">
                                <i class="fas fa-eraser"></i> Clear Chat
                            </button>
                            <button class="btn-admin btn-disable" id="toggleChatBtn">
                                <i class="fas fa-pause"></i> Disable Chat
                            </button>
                            <button class="btn-admin btn-slowmode" id="slowModeBtn">
                                <i class="fas fa-tachometer-alt"></i> Slow Mode
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
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
        
        <!-- Chat Settings Modal -->
        <div class="modal-overlay" id="chatSettingsModal" style="display: none;">
            <div class="modal-container">
                <div class="modal-header">
                    <h3><i class="fas fa-cog"></i> Chat Settings</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="chatSettingsForm">
                        <div class="form-group">
                            <label for="chatStatus">Chat Status</label>
                            <select id="chatStatus">
                                <option value="enabled">Enabled</option>
                                <option value="disabled">Disabled</option>
                                <option value="subscribers">Subscribers Only</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="slowMode">Slow Mode</label>
                            <select id="slowMode">
                                <option value="0">Off</option>
                                <option value="5">5 seconds</option>
                                <option value="10">10 seconds</option>
                                <option value="30">30 seconds</option>
                                <option value="60">1 minute</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="minLevel">Minimum Level to Chat</label>
                            <input type="number" id="minLevel" min="0" max="100" value="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="linkFiltering">Link Filtering</label>
                            <select id="linkFiltering">
                                <option value="none">Allow all links</option>
                                <option value="approved">Approved links only</option>
                                <option value="block">Block all links</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="enableAutoMod"> Enable Auto-Moderation
                            </label>
                        </div>
                        
                        <div class="modal-actions">
                            <button type="button" class="btn-admin btn-cancel">Cancel</button>
                            <button type="submit" class="btn-admin btn-submit">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Banned Words Modal -->
        <div class="modal-overlay" id="bannedWordsModal" style="display: none;">
            <div class="modal-container">
                <div class="modal-header">
                    <h3><i class="fas fa-ban"></i> Banned Words List</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="banned-words-controls">
                        <input type="text" id="newBannedWord" placeholder="Add new banned word">
                        <button class="btn-admin" id="addBannedWordBtn">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                    
                    <div class="banned-words-list">
                        <?php foreach ($bannedWords as $word): ?>
                            <div class="banned-word-item">
                                <span><?= htmlspecialchars($word) ?></span>
                                <button class="btn-admin btn-delete" data-word="<?= htmlspecialchars($word) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-admin btn-cancel">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ban User Modal -->
        <div class="modal-overlay" id="banUserModal" style="display: none;">
            <div class="modal-container">
                <div class="modal-header">
                    <h3><i class="fas fa-user-slash"></i> Ban User</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="banUserForm">
                        <div class="form-group">
                            <label for="banUsername">Username</label>
                            <input type="text" id="banUsername" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="banReason">Reason</label>
                            <input type="text" id="banReason" required placeholder="Violation of chat rules...">
                        </div>
                        
                        <div class="form-group">
                            <label for="banDuration">Duration</label>
                            <select id="banDuration">
                                <option value="1h">1 Hour</option>
                                <option value="1d">1 Day</option>
                                <option value="7d">7 Days</option>
                                <option value="30d">30 Days</option>
                                <option value="permanent">Permanent</option>
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

        <!-- Clear Chat Confirmation Modal -->
        <div class="modal-overlay" id="clearChatModal" style="display: none;">
            <div class="modal-container">
                <div class="modal-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Clear Chat</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to clear all chat messages? This action cannot be undone.</p>
                    <div class="modal-actions">
                        <button type="button" class="btn-admin btn-cancel">Cancel</button>
                        <button type="button" id="confirmClearChatBtn" class="btn-admin btn-delete">Clear Chat</button>
                    </div>
                </div>
            </div>
        </div>
            <!-- ========== Footer Start ========== -->
    <?php include_once('../blades/footer/footer.php'); ?>
    <!-- ========== Footer Ends ========== -->
    </main>

    <script src="/js/hk/chat-management.js"></script>
</body>
</html>