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

// Get user info
$userInfo = getUserInfo($_SESSION['user_id']);
if (!$userInfo) {
    header("Location: ../login.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        if (isset($_POST['update_user'])) {
            // Validate and sanitize inputs
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if (!$userId) throw new Exception('Invalid user ID');
            
            $username = htmlspecialchars(trim($_POST['username']));
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $role = htmlspecialchars(trim($_POST['role']));
            $status = htmlspecialchars(trim($_POST['status']));
            
            // Validate required fields
            if (!$username || !$email || !$role) {
                throw new Exception('All required fields must be filled');
            }
            
            // Handle numeric fields
            $discordId = !empty($_POST['discord_id']) ? filter_input(INPUT_POST, 'discord_id', FILTER_VALIDATE_INT) : null;
            $balance = !empty($_POST['balance']) ? filter_input(INPUT_POST, 'balance', FILTER_VALIDATE_FLOAT) : 0;
            
            // Handle discount field for resellers
            $discount = null;
            if ($role === 'reseller' && !empty($_POST['discount'])) {
                $discount = filter_input(INPUT_POST, 'discount', FILTER_VALIDATE_FLOAT);
                if ($discount === false || $discount < 0 || $discount > 100) {
                    throw new Exception('Discount must be a valid percentage between 0 and 100');
                }
            }

            // Validate role
            $validRoles = ['member', 'customer', 'media', 'reseller', 'support', 'developer', 'manager', 'founder'];
            if (!in_array($role, $validRoles)) {
                throw new Exception('Invalid role specified');
            }
            
            // Check email uniqueness
            $stmt = $pdo->prepare("SELECT id FROM usertable WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                throw new Exception('Email already exists for another user');
            }
            
            // Handle product access
            $productAccess = isset($_POST['productAccess']) ? $_POST['productAccess'] : [];
            $productAccessStr = !empty($productAccess) ? implode(',', array_map('htmlspecialchars', $productAccess)) : null;
            
            // Update user
            $stmt = $pdo->prepare("UPDATE usertable SET 
            name = ?, 
            email = ?, 
            role = ?, 
            banned = ?, 
            discordid = ?,
            balance = ?,
            discount_override = ?,
            product_access = ?,
            updated_at = NOW() 
            WHERE id = ?");
            
            $banned = ($status === 'banned') ? 1 : 0;
            
            $stmt->execute([
                $username, 
                $email, 
                $role, 
                $banned,
                $discordId,
                $balance,
                $discount,
                $productAccessStr,
                $userId
            ]);
            
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            exit();
            
        } elseif (isset($_POST['add_user'])) {
            // Add new user
            $username = htmlspecialchars(trim($_POST['username']));
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $role = htmlspecialchars(trim($_POST['role']));
            $status = htmlspecialchars(trim($_POST['status']));
            $productAccess = isset($_POST['productAccess']) ? $_POST['productAccess'] : [];
            $discordId = !empty($_POST['discord_id']) ? filter_input(INPUT_POST, 'discord_id', FILTER_VALIDATE_INT) : null;
            $balance = !empty($_POST['balance']) ? filter_input(INPUT_POST, 'balance', FILTER_VALIDATE_FLOAT) : 0;
            
            // Handle discount for resellers
            $discount = null;
            if ($role === 'reseller' && !empty($_POST['discount'])) {
                $discount = filter_input(INPUT_POST, 'discount', FILTER_VALIDATE_FLOAT);
                if ($discount === false || $discount < 0 || $discount > 100) {
                    throw new Exception('Discount must be a valid percentage between 0 and 100');
                }
            }
            
            $password = bin2hex(random_bytes(8)); // Generate random password
            
            if (!$username || !$email || !$role) {
                throw new Exception('All required fields must be filled');
            }
            
            // Validate role
            $validRoles = ['member', 'customer', 'media', 'reseller', 'support', 'developer', 'manager', 'founder'];
            if (!in_array($role, $validRoles)) {
                throw new Exception('Invalid role specified');
            }
            
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM usertable WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('Email already exists');
            }
            
            // Insert new user
            $stmt = $pdo->prepare("INSERT INTO usertable (
                name, 
                email, 
                role, 
                banned, 
                discordid,
                balance,
                discount_override,
                product_access,
                password, 
                created_at, 
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            
            $banned = ($status === 'banned') ? 1 : 0;
            $productAccessStr = !empty($productAccess) ? implode(',', $productAccess) : null;
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt->execute([
                $username, 
                $email, 
                $role, 
                $banned,
                $discordId,
                $balance,
                $discount,
                $productAccessStr,
                $hashedPassword
            ]);
            $userId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true, 
                'message' => 'User added successfully',
                'password' => $password
            ]);
            exit();
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build base query
$query = "SELECT * FROM usertable WHERE status != 'deleted'";
$countQuery = "SELECT COUNT(*) FROM usertable WHERE status != 'deleted'";
$where = [];
$params = [];
$countParams = [];

// Apply filters
if ($filter === 'active') {
    $where[] = "banned = 0";
} elseif ($filter === 'banned') {
    $where[] = "banned = 1";
} elseif ($filter === 'admins') {
    $where[] = "role IN ('support', 'developer', 'manager', 'founder')";
} elseif ($filter === 'customers') {
    $where[] = "(product_access IS NOT NULL OR role = 'customer')";
} elseif ($filter === 'resellers') {
    $where[] = "role = 'reseller'";
}

// Apply search
if ($search) {
    $where[] = "(name LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
}

// Combine where clauses
if (!empty($where)) {
    $query .= " AND " . implode(' AND ', $where);
    $countQuery .= " AND " . implode(' AND ', $where);
}

// Add sorting and pagination
$query .= " ORDER BY id ASC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;


// Get users
try {
    $stmt = $pdo->prepare($query);
    
    // Bind all where clause parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue(is_int($key) ? $key + 1 : $key, $value);
    }
    
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($countParams);
    $totalUsers = $stmt->fetchColumn();
    $totalPages = ceil($totalUsers / $perPage);
    
    // Get counts for each filter
    $filterCounts = [
        'all' => 0,
        'active' => 0,
        'banned' => 0,
        'admins' => 0,
        'customers' => 0,
        'resellers' => 0
    ];
    
    // Get all users count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usertable WHERE status != 'deleted'");
    $stmt->execute();
    $filterCounts['all'] = $stmt->fetchColumn();
    
    // Get active users count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usertable WHERE banned = 0 AND status != 'deleted'");
    $stmt->execute();
    $filterCounts['active'] = $stmt->fetchColumn();
    
    // Get banned users count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usertable WHERE banned = 1 AND status != 'deleted'");
    $stmt->execute();
    $filterCounts['banned'] = $stmt->fetchColumn();
    
    // Get admin users count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usertable WHERE role IN ('support', 'developer', 'manager', 'founder') AND status != 'deleted'");
    $stmt->execute();
    $filterCounts['admins'] = $stmt->fetchColumn();
    
    // Get customers count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usertable WHERE (product_access IS NOT NULL OR role = 'customer') AND status != 'deleted'");
    $stmt->execute();
    $filterCounts['customers'] = $stmt->fetchColumn();
    
    // Get resellers count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usertable WHERE role = 'reseller' AND status != 'deleted'");
    $stmt->execute();
    $filterCounts['resellers'] = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $error = "Failed to load users: " . $e->getMessage();
    $users = [];
    $totalPages = 1;
    $filterCounts = [
        'all' => 0,
        'active' => 0,
        'banned' => 0,
        'admins' => 0,
        'customers' => 0,
        'resellers' => 0
    ];
}

// Get all products for the add/edit modal
$products = $pdo->query("SELECT id, name FROM products WHERE visibility = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/hk/dashboard.css">
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
                    <a href="dashboard.php" class="breadcrumb-item">Admin</a>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-current">User Management</span>
                </div>
            </div>
            <?php include_once('../blades/notify/notify.php'); ?>
        </header>

        <div class="content-area-wrapper">
            <div class="hk-hero">
                <div class="banner-content">
                    <div class="hk-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <h1>User Management</h1>
                    <p class="hk-subtitle">Manage user accounts and permissions</p>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="table-controls">
                <div class="controls-left">
                    <div class="filter-options">
                        <button class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>" data-filter="all">All Users (<?= $filterCounts['all'] ?>)</button>
                        <button class="filter-btn <?= $filter === 'active' ? 'active' : '' ?>" data-filter="active">Active (<?= $filterCounts['active'] ?>)</button>
                        <button class="filter-btn <?= $filter === 'banned' ? 'active' : '' ?>" data-filter="banned">Banned (<?= $filterCounts['banned'] ?>)</button>
                        <button class="filter-btn <?= $filter === 'admins' ? 'active' : '' ?>" data-filter="admins">Admins (<?= $filterCounts['admins'] ?>)</button>
                        <button class="filter-btn <?= $filter === 'customers' ? 'active' : '' ?>" data-filter="customers">Customers (<?= $filterCounts['customers'] ?>)</button>
                        <button class="filter-btn <?= $filter === 'resellers' ? 'active' : '' ?>" data-filter="resellers">Resellers (<?= $filterCounts['resellers'] ?>)</button>
                    </div>
                </div>
                <div class="controls-right">
                    <form method="GET" action="users.php" class="search-form">
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search users..." id="userSearch" value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </form>
                    <button class="btn-admin btn-edit" id="addUserBtn">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                </div>
            </div>

            <table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Discord ID</th>
            <th>Balance</th>
            <th>Role</th>
            <th>Discount</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($users)): ?>
            <tr>
                <td colspan="10" class="text-center">No users found</td>
            </tr>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['id']) ?></td>
                    <td>
                        <span class="user-avatar"><i class="fas fa-user"></i></span>
                        <?= htmlspecialchars($user['name']) ?>
                    </td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= ($user['discordid'] == 0 || $user['discordid'] === null) ? 'Not Linked' : htmlspecialchars($user['discordid']) ?></td>
                    <td>$<?= number_format($user['balance'] ?? 0, 2) ?></td>
                    <td>
                        <span class="role-badge role-<?= $user['role']; ?>">
                            <?= ucfirst(htmlspecialchars($user['role'])) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($user['role'] === 'reseller' && $user['discount_override'] !== null): ?>
                            <?= number_format($user['discount_override'], 2) ?>%  <!-- Fixed variable name -->
                        <?php else: ?>
                            0
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['banned']): ?>
                            <span class="status-badge status-expired">Banned</span>
                        <?php else: ?>
                            <span class="status-badge status-active">Active</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                    <td>
                                        <div class="admin-actions">
                                            <button class="btn-admin btn-edit" title="Edit" data-user-id="<?= $user['id'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($user['banned']): ?>
                                                <button class="btn-admin btn-unban" title="Unban" data-user-id="<?= $user['id'] ?>">
                                                    <i class="fas fa-unlock"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-admin btn-ban" title="Ban" data-user-id="<?= $user['id'] ?>">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn-admin btn-delete" title="Delete" data-user-id="<?= $user['id'] ?>">
                                                <i class="fas fa-trash"></i>
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
                    <a href="?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>" class="pagination-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="pagination-btn disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>

                <?php
                // Show first page
                if ($page > 3): ?>
                    <a href="?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&page=1" class="pagination-btn">1</a>
                    <?php if ($page > 4): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php
                // Show pages around current page
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="pagination-btn active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>" class="pagination-btn"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php
                // Show last page
                if ($page < $totalPages - 2): ?>
                    <?php if ($page < $totalPages - 3): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                    <a href="?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&page=<?= $totalPages ?>" class="pagination-btn"><?= $totalPages ?></a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>" class="pagination-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="pagination-btn disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php include_once('../blades/footer/footer.php'); ?>

    <!-- Edit User Modal -->
    <div class="modal-overlay" id="editUserModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Edit User</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editUserForm" method="POST" action="users.php">
                    <input type="hidden" name="update_user" value="1">
                    <input type="hidden" name="user_id" id="editUserId">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editUsername">Username</label>
                            <input type="text" id="editUsername" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="editEmail">Email</label>
                            <input type="email" id="editEmail" name="email" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editDiscordId">Discord ID</label>
                            <input type="number" id="editDiscordId" name="discord_id">
                        </div>
                        <div class="form-group">
                            <label for="editBalance">Balance</label>
                            <input type="number" step="0.01" id="editBalance" name="balance">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editRole">Role</label>
                            <select id="editRole" name="role" required onchange="toggleDiscountField()">
                                <option value="member">Member</option>
                                <option value="customer">Customer</option>
                                <option value="media">Media</option>
                                <option value="reseller">Reseller</option>
                                <option value="support">Support</option>
                                <option value="developer">Developer</option>
                                <option value="manager">Manager</option>
                                <option value="founder">Founder</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editStatus">Status</label>
                            <select id="editStatus" name="status" required>
                                <option value="active">Active</option>
                                <option value="banned">Banned</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Discount field for resellers -->
                    <div class="form-group" id="discountField" style="display: none;">
                        <label for="editDiscount">Discount Percentage</label>
                        <input type="number" step="0.01" min="0" max="100" id="editDiscount" name="discount" placeholder="0-100%">
                        <small>Enter a discount percentage for this reseller (0-100)</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Product Access</label>
                        <div class="checkbox-group" id="productAccessContainer">
                            <?php foreach ($products as $product): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="productAccess[]" value="<?= htmlspecialchars($product['name']) ?>">
                                    <?= htmlspecialchars($product['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-admin btn-cancel">Cancel</button>
                        <button type="submit" class="btn-admin btn-submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- Add User Modal -->
    <div class="modal-overlay" id="addUserModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New User</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addUserForm" method="POST" action="users.php">
                    <input type="hidden" name="add_user" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="addUsername">Username</label>
                            <input type="text" id="addUsername" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="addEmail">Email</label>
                            <input type="email" id="addEmail" name="email" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="addDiscordId">Discord ID</label>
                            <input type="number" id="addDiscordId" name="discord_id">
                        </div>
                        <div class="form-group">
                            <label for="addBalance">Balance</label>
                            <input type="number" step="0.01" id="addBalance" name="balance" value="0">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="addRole">Role</label>
                            <select id="addRole" name="role" required onchange="toggleDiscountField('add')">
                                <option value="member">Member</option>
                                <option value="customer">Customer</option>
                                <option value="media">Media</option>
                                <option value="reseller">Reseller</option>
                                <option value="support">Support</option>
                                <option value="developer">Developer</option>
                                <option value="manager">Manager</option>
                                <option value="founder">Founder</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="addStatus">Status</label>
                            <select id="addStatus" name="status" required>
                                <option value="active">Active</option>
                                <option value="banned">Banned</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Discount field for resellers -->
                    <div class="form-group" id="addDiscountField" style="display: none;">
                        <label for="addDiscount">Discount Percentage</label>
                        <input type="number" step="0.01" min="0" max="100" id="addDiscount" name="discount" placeholder="0-100%">
                        <small>Enter a discount percentage for this reseller (0-100)</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Product Access</label>
                        <div class="checkbox-group">
                            <?php foreach ($products as $product): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="productAccess[]" value="<?= htmlspecialchars($product['name']) ?>">
                                    <?= htmlspecialchars($product['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-admin btn-cancel">Cancel</button>
                        <button type="submit" class="btn-admin btn-submit">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

        <!-- Ban User Modal -->
        <div class="modal-overlay" id="banUserModal">
            <div class="modal-container">
                <div class="modal-header">
                    <h3><i class="fas fa-ban"></i> Ban User</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="banUserForm" method="POST" action="users.php">
                        <input type="hidden" name="ban_user" value="1">
                        <input type="hidden" name="user_id" id="banUserId">
                        <div class="form-group">
                            <label for="banReason">Reason</label>
                            <input type="text" id="banReason" name="reason" required placeholder="Violation of terms...">
                        </div>
                        
                        <div class="form-group">
                            <label for="banDuration">Duration</label>
                            <select id="banDuration" name="duration">
                                <option value="permanent">Permanent</option>
                                <option value="1d">1 Day</option>
                                <option value="7d">7 Days</option>
                                <option value="30d">30 Days</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="customBanDateGroup" style="display: none;">
                            <label for="customBanDate">Custom End Date</label>
                            <input type="datetime-local" id="customBanDate" name="custom_date">
                        </div>
                        
                        <div class="modal-actions">
                            <button type="button" class="btn-admin btn-cancel">Cancel</button>
                            <button type="submit" class="btn-admin btn-submit">Confirm Ban</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Unban User Modal -->
        <div class="modal-overlay" id="unbanUserModal">
            <div class="modal-container">
                <div class="modal-header">
                    <h3><i class="fas fa-unlock"></i> Unban User</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="unbanUserForm" method="POST" action="users.php">
                        <input type="hidden" name="unban_user" value="1">
                        <input type="hidden" name="user_id" id="unbanUserId">
                        <p>Are you sure you want to unban this user?</p>
                        <div class="modal-actions">
                            <button type="button" class="btn-admin btn-cancel">Cancel</button>
                            <button type="submit" class="btn-admin btn-submit">Confirm Unban</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete User Modal -->
        <div class="modal-overlay" id="deleteUserModal">
            <div class="modal-container">
                <div class="modal-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="deleteUserForm" method="POST" action="users.php">
                        <input type="hidden" name="delete_user" value="1">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <p>Are you sure you want to permanently delete this user account? This action cannot be undone.</p>
                        <div class="modal-actions">
                            <button type="button" class="btn-admin btn-cancel">Cancel</button>
                            <button type="submit" class="btn-admin btn-delete">Delete Permanently</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
    // Function to toggle discount field based on role selection
    function toggleDiscountField(formType = 'edit') {
        const roleSelect = document.getElementById(formType === 'add' ? 'addRole' : 'editRole');
        const discountField = document.getElementById(formType === 'add' ? 'addDiscountField' : 'discountField');
        
        if (roleSelect.value === 'reseller') {
            discountField.style.display = 'block';
        } else {
            discountField.style.display = 'none';
        }
    }
    
    // Initialize discount field visibility on page load
    document.addEventListener('DOMContentLoaded', function() {
        toggleDiscountField('edit');
        toggleDiscountField('add');
    });
    </script>
    <script src="../js/hk/user-management.js"></script>
</body>
</html>