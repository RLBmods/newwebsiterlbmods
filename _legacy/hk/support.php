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

// Handle AJAX request for ticket data
if (isset($_GET['ajax_ticket'])) {
    $ticketId = filter_input(INPUT_GET, 'ticket_id', FILTER_VALIDATE_INT);
    if ($ticketId) {
        try {
            // Get ticket info
            $stmt = $pdo->prepare("SELECT t.*, 
                (SELECT tm.username FROM ticket_messages tm WHERE tm.ticket_id = t.id AND tm.is_support = 0 LIMIT 1) as customer_name
                FROM tickets t
                WHERE t.id = ?");
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch();
            
            if ($ticket) {
                // Get messages with user info
                $stmt = $pdo->prepare("SELECT tm.*, u.name, u.role 
                    FROM ticket_messages tm
                    LEFT JOIN usertable u ON tm.user_id = u.id
                    WHERE tm.ticket_id = ? 
                    ORDER BY tm.created_at ASC");
                $stmt->execute([$ticketId]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get assigned staff
                $stmt = $pdo->prepare("SELECT u.id, u.name, u.role 
                    FROM ticket_assignments ta
                    JOIN usertable u ON ta.staff_id = u.id
                    WHERE ta.ticket_id = ?");
                $stmt->execute([$ticketId]);
                $assignedStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get available staff members (including all support roles)
                $stmt = $pdo->prepare("SELECT id, name, role 
                    FROM usertable 
                    WHERE role IN ('support', 'developer', 'manager', 'founder', 'admin')");
                $stmt->execute();
                $staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'ticket' => $ticket,
                    'messages' => $messages,
                    'assignedStaff' => $assignedStaff,
                    'staffMembers' => $staffMembers
                ]);
                exit();
            }
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            exit();
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid ticket']);
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_ticket_status'])) {
        // Update ticket status
        $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
        $status = filter_input(INPUT_POST, 'status', FILTER_DEFAULT);
        
        if ($ticketId && $status) {
            try {
                $validStatuses = ['Open', 'Pending', 'Resolved', 'Closed'];
                if (!in_array($status, $validStatuses)) {
                    throw new Exception('Invalid status');
                }
                
                $stmt = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $ticketId]);
                
                logAction($_SESSION['user_id'], "Changed ticket #$ticketId status to $status", $ticketId);
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit();
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit();
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid ticket ID or status']);
        exit();
    }
 // For assign_ticket
elseif (isset($_POST['assign_ticket'])) {
    header('Content-Type: application/json');
    
    $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
    $staffId = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
    
    if (!$ticketId || !$staffId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid ticket or staff ID']);
        exit();
    }

    try {
        // Verify staff exists and has proper role
        $stmt = $pdo->prepare("SELECT id FROM usertable WHERE id = ? AND role IN ('support', 'developer', 'manager', 'founder', 'admin')");
        $stmt->execute([$staffId]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Invalid staff member');
        }
        
        // Check if already assigned
        $stmt = $pdo->prepare("SELECT id FROM ticket_assignments WHERE ticket_id = ? AND staff_id = ?");
        $stmt->execute([$ticketId, $staffId]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO ticket_assignments (ticket_id, staff_id, assigned_by, assigned_at) 
                                 VALUES (?, ?, ?, NOW())");
            $stmt->execute([$ticketId, $staffId, $_SESSION['user_id']]);
            
            logAction($_SESSION['user_id'], "Assigned ticket #$ticketId to staff ID $staffId", $ticketId);
            
            // Also update ticket status if it was unassigned
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'Pending', updated_at = NOW() 
                                 WHERE id = ? AND status = 'Open'");
            $stmt->execute([$ticketId]);
        }
        
        echo json_encode(['success' => true]);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// For remove_assignment
elseif (isset($_POST['remove_assignment'])) {
    header('Content-Type: application/json');
    
    $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
    $staffId = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
    
    if (!$ticketId || !$staffId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid ticket or staff ID']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM ticket_assignments WHERE ticket_id = ? AND staff_id = ?");
        $stmt->execute([$ticketId, $staffId]);
        
        // Check if no more assignments exist
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ticket_assignments WHERE ticket_id = ?");
        $stmt->execute([$ticketId]);
        $assignmentCount = $stmt->fetchColumn();
        
        // If no more assignments, set status back to Open
        if ($assignmentCount == 0) {
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'Open', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$ticketId]);
        }
        
        logAction($_SESSION['user_id'], "Removed staff ID $staffId from ticket #$ticketId", $ticketId);
        
        echo json_encode(['success' => true]);
        exit();
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}
    elseif (isset($_POST['reply_ticket'])) {
        // Add reply to ticket
        $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
        $replyMessage = filter_input(INPUT_POST, 'reply_message', FILTER_SANITIZE_STRING);
        $status = filter_input(INPUT_POST, 'reply_status', FILTER_SANITIZE_STRING);
        
        $response = ['success' => false, 'error' => ''];
        
        if ($ticketId) {
            try {
                // Handle file upload
                $attachmentPath = null;
                if (!empty($_FILES['reply-attachments']['name'][0])) {
                    $uploadDir = '../uploads/tickets/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $filename = uniqid() . '_' . basename($_FILES['reply-attachments']['name'][0]);
                    $targetPath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($_FILES['reply-attachments']['tmp_name'][0], $targetPath)) {
                        $attachmentPath = $targetPath;
                    }
                }

                // Add reply as support
                $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, username, message, is_support, attachment) 
                                     VALUES (?, ?, ?, ?, 1, ?)");
                $stmt->execute([
                    $ticketId, 
                    $_SESSION['user_id'],
                    $userInfo['username'],
                    $replyMessage,
                    $attachmentPath
                ]);
                
                // Update ticket status if changed
                if ($status) {
                    $stmt = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$status, $ticketId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$ticketId]);
                }
                
                logAction($_SESSION['user_id'], "Replied to ticket #$ticketId", $ticketId);
                
                $response['success'] = true;
                $response['message'] = "Reply sent successfully";
                
                // Return JSON for AJAX
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit();
                }
                
                header("Location: support.php?view=$ticketId&replied=1");
                exit();
            } catch (PDOException $e) {
                $response['error'] = "Database error: " . $e->getMessage();
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode($response);
                exit();
            }
        } else {
            $response['error'] = 'Invalid ticket ID';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
    }
}

// Get all tickets for admin view
try {
    // Base query
    $query = "SELECT t.*, 
        (SELECT username FROM ticket_messages WHERE ticket_id = t.id AND is_support = 0 LIMIT 1) as customer_name,
        (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id AND tm.is_support = 0) as unread_replies
        FROM tickets t";
    
    // Add filters if any
    $params = [];
    $where = [];
    
    if (isset($_GET['filter'])) {
        $filter = $_GET['filter'];
        if (in_array($filter, ['open', 'pending', 'resolved', 'closed'])) {
            $where[] = "t.status = ?";
            $params[] = ucfirst($filter);
        } elseif ($filter === 'unassigned') {
            $where[] = "NOT EXISTS (SELECT 1 FROM ticket_assignments ta WHERE ta.ticket_id = t.id)";
        } elseif ($filter === 'my') {
            $where[] = "EXISTS (SELECT 1 FROM ticket_assignments ta WHERE ta.ticket_id = t.id AND ta.staff_id = ?)";
            $params[] = $_SESSION['user_id'];
        }
    }
    
    if (!empty($_GET['search'])) {
        $where[] = "(t.subject LIKE ? OR t.message LIKE ?)";
        $searchTerm = '%' . $_GET['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($where)) {
        $query .= " WHERE " . implode(' AND ', $where);
    }
    
    // Add sorting
    $query .= " ORDER BY 
                CASE WHEN t.status = 'Open' THEN 1
                     WHEN t.status = 'Pending' THEN 2
                     WHEN t.status = 'Resolved' THEN 3
                     WHEN t.status = 'Closed' THEN 4
                     ELSE 5 END,
                t.updated_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get ticket counts by status and assignment
    $statusCounts = [
        'Open' => 0,
        'Pending' => 0,
        'Resolved' => 0,
        'Closed' => 0,
        'unassigned' => 0,
        'my' => 0
    ];
    
    // Get counts for status filters
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM tickets GROUP BY status");
    $stmt->execute();
    $statusResults = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    foreach ($statusResults as $status => $count) {
        if (array_key_exists($status, $statusCounts)) {
            $statusCounts[$status] = $count;
        }
    }
    
    // Get count for unassigned tickets
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE NOT EXISTS (SELECT 1 FROM ticket_assignments ta WHERE ta.ticket_id = tickets.id)");
    $stmt->execute();
    $statusCounts['unassigned'] = $stmt->fetchColumn();
    
    // Get count for my tickets
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets t WHERE EXISTS (SELECT 1 FROM ticket_assignments ta WHERE ta.ticket_id = t.id AND ta.staff_id = ?)");
    $stmt->execute([$_SESSION['user_id']]);
    $statusCounts['my'] = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $error = "Failed to load tickets: " . $e->getMessage();
}

// Get count of unreplied tickets
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as unreplied 
        FROM tickets 
        WHERE status = 'Open' 
        AND NOT EXISTS (
            SELECT 1 FROM ticket_messages tm 
            WHERE tm.ticket_id = tickets.id AND tm.is_support = 1
        )");
    $stmt->execute();
    $unrepliedCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    $unrepliedCount = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name; ?> • Support Center</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/hk/support.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.11.2/css/all.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/hk/support.js" defer></script>
    <script src="../js/heartbeat.js" defer></script>
    <script src="../js/notify.js" defer></script>
</head>
<body>
    <!-- ========== Left Sidebar Start ========== -->
    <?php include_once('../blades/sidebar/hk-sidebar.php'); ?>
        <!-- ========== Left Sidebar Ends ========== -->
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <div class="breadcrumbs">
                    <a href="/" class="breadcrumb-item"><i class="fas fa-home"></i></a>
                    <a href="dashboard.php" class="breadcrumb-item">Admin</a>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-current">Support Center</span>
                </div>
            </div>

        <!-- ========== Left Sidebar Start ========== -->
        <?php include_once('../blades/notify/notify.php'); ?>
        <!-- ========== Left Sidebar Ends ========== -->
        </header>
        
        <div class="support-wrapper">
            <!-- Support Hero Section -->
            <div class="support-hero">
                <div class="support-hero-content">
                    <div class="support-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h2>Support Center</h2>
                    <p class="support-subtitle">There is currently <?= $unrepliedCount ?> unreplied tickets</p>
                </div>
            </div>
            
            <?php if (isset($_GET['created'])): ?>
                <div class="alert alert-success">
                    Ticket #<?= htmlspecialchars($_GET['created']) ?> created successfully!
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <div class="support-grid">
                <!-- Tickets Column -->
                <div class="support-main">
                    
            <!-- Tickets Filter -->
            <div class="tickets-filter">
    <div class="filter-options">
        <button class="filter-btn <?= !isset($_GET['filter']) ? 'active' : '' ?>" data-filter="all">All Tickets (<?= count($tickets) ?>)</button>
        <button class="filter-btn <?= isset($_GET['filter']) && $_GET['filter'] === 'open' ? 'active' : '' ?>" data-filter="open">Open (<?= $statusCounts['Open'] ?? 0 ?>)</button>
        <button class="filter-btn <?= isset($_GET['filter']) && $_GET['filter'] === 'pending' ? 'active' : '' ?>" data-filter="pending">Pending (<?= $statusCounts['Pending'] ?? 0 ?>)</button>
        <button class="filter-btn <?= isset($_GET['filter']) && $_GET['filter'] === 'resolved' ? 'active' : '' ?>" data-filter="resolved">Resolved (<?= $statusCounts['Resolved'] ?? 0 ?>)</button>
        <button class="filter-btn <?= isset($_GET['filter']) && $_GET['filter'] === 'closed' ? 'active' : '' ?>" data-filter="closed">Closed (<?= $statusCounts['Closed'] ?? 0 ?>)</button>
        <button class="filter-btn <?= isset($_GET['filter']) && $_GET['filter'] === 'unassigned' ? 'active' : '' ?>" data-filter="unassigned">Unassigned (<?= $statusCounts['unassigned'] ?? 0 ?>)</button>
        <button class="filter-btn <?= isset($_GET['filter']) && $_GET['filter'] === 'my' ? 'active' : '' ?>" data-filter="my">My Tickets (<?= $statusCounts['my'] ?? 0 ?>)</button>
    </div>
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="ticketSearch" placeholder="Search tickets..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
    </div>
</div>
                    
            <!-- Tickets List -->
            <div class="tickets-list">
                <?php if (empty($tickets)): ?>
                    <div class="empty-state">
                        <i class="fas fa-ticket-alt"></i>
                        <p>No tickets found matching your criteria.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tickets as $ticket): ?>
                        <div class="ticket-card <?= $ticket['unread_replies'] > 0 ? 'unread' : '' ?>">
                            <div class="ticket-header">
                                <span class="ticket-id">#TKT-<?= str_pad($ticket['id'], 5, '0', STR_PAD_LEFT) ?></span>
                                <span class="ticket-status status-<?= strtolower($ticket['status']) ?>">
                                    <?= htmlspecialchars($ticket['status']) ?>
                                </span>
                            </div>
                            <h3 class="ticket-title"><?= htmlspecialchars(substr($ticket['subject'], 0, 30)) ?></h3>
                            <p class="ticket-desc"><?= htmlspecialchars(substr($ticket['message'], 0, 100)) ?>...</p>
                            <div class="ticket-meta">
                                <span class="meta-item"><i class="fas fa-user"></i> <?= htmlspecialchars($ticket['customer_name'] ?? 'Unknown') ?></span>
                                <span class="meta-item"><i class="fas fa-calendar"></i> <?= time_elapsed_string($ticket['created_at']) ?></span>
                                <span class="meta-item"><i class="fas fa-tag"></i> <?= htmlspecialchars($ticket['type']) ?></span>
                                <?php if ($ticket['unread_replies'] > 0): ?>
                                    <span class="meta-item unread-count"><i class="fas fa-envelope"></i> <?= $ticket['unread_replies'] ?> new</span>
                                <?php endif; ?>
                            </div>
                            <button class="btn-secondary view-ticket-btn" data-ticket-id="<?= $ticket['id'] ?>">View Ticket</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
                </div>
                
        <!-- Help Column -->
        <div class="support-sidebar">
            <div class="support-card">
                <div class="support-card-header">
                    <h3><i class="fas fa-chart-pie"></i> Ticket Statistics</h3>
                </div>
                <div class="support-card-body">
                    <div class="solution-item">
                        <div class="ticket-stats">
                            <div class="ticket-item">
                                <span class="stat-label">Open</span>
                                <span class="stat-value"><?= $statusCounts['Open'] ?? 0 ?></span>
                            </div>
                            <div class="ticket-item">
                                <span class="stat-label">Pending</span>
                                <span class="stat-value"><?= $statusCounts['Pending'] ?? 0 ?></span>
                            </div>
                            <div class="ticket-item">
                                <span class="stat-label">Resolved</span>
                                <span class="stat-value"><?= $statusCounts['Resolved'] ?? 0 ?></span>
                            </div>
                            <div class="ticket-item">
                                <span class="stat-label">Closed</span>
                                <span class="stat-value"><?= $statusCounts['Closed'] ?? 0 ?></span>
                            </div>
                            <div class="ticket-item">
                                <span class="stat-label">Unassigned</span>
                                <span class="stat-value"><?= $statusCounts['Unassigned'] ?? 0 ?></span>
                            </div>
                            <div class="ticket-item">
                                <span class="stat-label">My Tickets</span>
                                <span class="stat-value"><?= $statusCounts['my'] ?? 0 ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
            </div>
            
    <!-- Ticket Detail Modal -->
    <div class="modal" id="ticketDetailModal">
        <div class="modal-content ticket-detail-content">
            <div class="modal-header">
                <h3><i class="fas fa-ticket-alt"></i> Ticket Details</h3>
                <button class="modal-close" id="detailModalClose">&times;</button>
            </div>
            <div class="modal-body" id="ticketModalContent">
                <!-- Content loaded dynamically here -->
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i> Loading ticket details...
                </div>
            </div>
        </div>
    </div>
        </div>
        
    <!-- ========== Footer Start ========== -->
    <?php include_once('../blades/footer/footer.php'); ?>
    <!-- ========== Footer Ends ========== -->
    </main>

    <script>
        // Initialize filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const filter = this.dataset.filter;
                const search = document.getElementById('ticketSearch').value;
                
                let url = 'support.php';
                const params = [];
                
                if (filter !== 'all') {
                    params.push(`filter=${filter}`);
                }
                
                if (search) {
                    params.push(`search=${encodeURIComponent(search)}`);
                }
                
                if (params.length > 0) {
                    url += '?' + params.join('&');
                }
                
                window.location.href = url;
            });
        });
        
        // Initialize search box
        document.getElementById('ticketSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const filter = document.querySelector('.filter-btn.active').dataset.filter;
                const search = this.value;
                
                let url = 'support.php';
                const params = [];
                
                if (filter !== 'all') {
                    params.push(`filter=${filter}`);
                }
                
                if (search) {
                    params.push(`search=${encodeURIComponent(search)}`);
                }
                
                if (params.length > 0) {
                    url += '?' + params.join('&');
                }
                
                window.location.href = url;
            }
        });
    </script>
</body>
</html>