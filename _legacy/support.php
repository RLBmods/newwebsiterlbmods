<?php
// Enable output buffering
ob_start();

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once './includes/session.php';
require_once './includes/functions.php';
require_once './db/connection.php';
require_once './includes/logging.php';
require_once './includes/get_user_info.php';

// Authentication
requireAuth();
requireMember();

// Get user info
$userInfo = getUserInfo($_SESSION['user_id']);
if (!$userInfo) {
    header("Location: login.php");
    exit();
}

// Handle AJAX request for ticket data
if (isset($_GET['ajax_ticket'])) {
    $ticketId = filter_input(INPUT_GET, 'ticket_id', FILTER_VALIDATE_INT);
    if ($ticketId) {
        try {
            // Get ticket info
            $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ? AND customer_id = ?");
            $stmt->execute([$ticketId, $_SESSION['user_id']]);
            $ticket = $stmt->fetch();
            
            if ($ticket) {
                // Get messages with user roles for support members
                $stmt = $pdo->prepare("SELECT tm.*, ut.role 
                                      FROM ticket_messages tm
                                      LEFT JOIN usertable ut ON tm.user_id = ut.id AND tm.is_support = 1
                                      WHERE tm.ticket_id = ? 
                                      ORDER BY tm.created_at ASC");
                $stmt->execute([$ticketId]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'ticket' => $ticket,
                    'messages' => $messages
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
    if (isset($_POST['create_ticket'])) {
        // Create new ticket
        $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);
        $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
        $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
        
        // Map category to ticket type
        $typeMap = [
            'billing' => 'Billing',
            'account' => 'Support',
            'technical' => 'Support',
            'product' => 'Support',
            'other' => 'Other'
        ];
        
        $type = $typeMap[$category] ?? 'Other';
        
        try {
            // Handle file upload
            $attachmentPath = null;
            if (!empty($_FILES['ticket-attachments']['name'][0])) {
                $uploadDir = './uploads/tickets/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $filename = uniqid() . '_' . basename($_FILES['ticket-attachments']['name'][0]);
                $targetPath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['ticket-attachments']['tmp_name'][0], $targetPath)) {
                    $attachmentPath = $targetPath;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO tickets (subject, type, priority, message, customer_id, status) 
                                 VALUES (?, ?, 'Normal', ?, ?, 'Open')");
            $stmt->execute([$subject, $type, $message, $_SESSION['user_id']]);
            $ticketId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, username, message, is_support, attachment) 
            VALUES (?, ?, ?, ?, 0, ?)");
            
            $stmt->execute([
                $ticketId, 
                $_SESSION['user_id'],
                $userInfo['username'],
                $message,
                $attachmentPath
            ]);

            // Log the ticket creation
            logAction($_SESSION['user_id'], "Created ticket #$ticketId", $ticketId);
            
            // Redirect to prevent form resubmission
            header("Location: support.php?created=$ticketId");
            exit();
        } catch (PDOException $e) {
            $error = "Failed to create ticket: " . $e->getMessage();
        }
    } elseif (isset($_POST['reply_ticket'])) {
        // Add reply to ticket
        $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
        $replyMessage = filter_input(INPUT_POST, 'reply_message', FILTER_SANITIZE_STRING);
        
        // Initialize response array
        $response = ['success' => false, 'error' => ''];
        
        if ($ticketId) {
            try {
                
                // Verify user owns the ticket
                $stmt = $pdo->prepare("SELECT customer_id FROM tickets WHERE id = ?");
                $stmt->execute([$ticketId]);
                $ticket = $stmt->fetch();
                
            // Handle file upload
            $attachmentPath = null;
            if (!empty($_FILES['reply-attachments']['name'][0])) {
                $uploadDir = './uploads/tickets/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $filename = uniqid() . '_' . basename($_FILES['reply-attachments']['name'][0]);
                $targetPath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['reply-attachments']['tmp_name'][0], $targetPath)) {
                    $attachmentPath = $targetPath;
                }
            }

                if ($ticket && $ticket['customer_id'] == $_SESSION['user_id']) {
                    // Add reply
                    $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, username, message, is_support, attachment) 
                                VALUES (?, ?, ?, ?, 0, ?)");
            $stmt->execute([
                $ticketId, 
                $_SESSION['user_id'],
                $userInfo['username'],
                $replyMessage,
                $attachmentPath
            ]);
                    
                    // Update ticket status
                    $stmt = $pdo->prepare("UPDATE tickets SET status = 'Open', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$ticketId]);
                    
                    // Log the reply
                    logAction($_SESSION['user_id'], "Replied to ticket #$ticketId", $ticketId);
                    
                    // Set success response
                    $response['success'] = true;
                    
                    // Return JSON for AJAX or redirect
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit();
                    }
                    
                    header("Location: support.php?view=$ticketId&replied=1");
                    exit();
                } else {
                    $response['error'] = 'Ticket not found or access denied';
                }
            } catch (PDOException $e) {
                $response['error'] = "Database error: " . $e->getMessage();
            }
        } else {
            $response['error'] = 'Invalid ticket ID';
        }
        
        // Return error response
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        
        // Fallback for non-AJAX requests
        $error = $response['error'] ?: 'Failed to add reply';
    }
}

// Get user's tickets
try {
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get ticket counts by status
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM tickets WHERE customer_id = ? GROUP BY status");
    $stmt->execute([$_SESSION['user_id']]);
    $statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $error = "Failed to load tickets: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name;?> • Support Center</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/support.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.11.2/css/all.css">
    <script src="js/heartbeat.js" defer></script>
    <script src="js/notify.js" defer></script>
</head>
<body>
<body data-username="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>">
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
                    <span class="breadcrumb-current">Support Center</span>
                </div>
            </div>

        <!-- ========== Left Sidebar Start ========== -->
        <?php include_once('./blades/notify/notify.php'); ?>
        <!-- ========== Left Sidebar Ends ========== -->

        </header>

        <div class="support-wrapper">
            <!-- Support Hero Section -->
            <div class="support-hero">
                <div class="support-hero-content">
                    <div class="support-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h2>How can we help you?</h2>
                    <p class="support-subtitle">Submit a new ticket or check the status of existing ones</p>
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
                    
                    <div class="tickets-filter">
                        
                        <div class="filter-options">
                            <button class="filter-btn active" data-filter="all">All Tickets (<?= count($tickets) ?>)</button>
                            <button class="filter-btn" data-filter="open">Open (<?= $statusCounts['Open'] ?? 0 ?>)</button>
                            <button class="filter-btn" data-filter="answered">Answered (<?= $statusCounts['Answered'] ?? 0 ?>)</button>
                            <button class="filter-btn" data-filter="closed">Closed (<?= $statusCounts['Closed'] ?? 0 ?>)</button>
                        </div>

                        <button class="btn-primary new-ticket-btn" id="newTicketBtn">
                            <i class="fas fa-plus"></i> Create New Ticket
                        </button>

                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search tickets...">
                        </div>
                    </div>
                    
                    <div class="tickets-list">
                        <?php if (empty($tickets)): ?>
                            <div class="empty-state">
                                <i class="fas fa-ticket-alt"></i>
                                <p>You haven't created any tickets yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket): ?>
                                <div class="ticket-card">
                                    <div class="ticket-header">
                                        <span class="ticket-id">#TKT-<?= str_pad($ticket['id'], 5, '0', STR_PAD_LEFT) ?></span>
                                        <span class="ticket-status status-<?= strtolower($ticket['status']) ?>">
                                            <?= htmlspecialchars($ticket['status']) ?>
                                        </span>
                                    </div>
                                    <h3 class="ticket-title"><?= htmlspecialchars($ticket['subject']) ?></h3>
                                    <p class="ticket-desc"><?= htmlspecialchars(substr($ticket['message'], 0, 100)) ?>...</p>
                                    <div class="ticket-meta">
                                        <span class="meta-item"><i class="fas fa-calendar"></i> <?= time_elapsed_string($ticket['created_at']) ?></span>
                                        <span class="meta-item"><i class="fas fa-tag"></i> <?= htmlspecialchars($ticket['type']) ?></span>
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
                            <h3><i class="fas fa-lightbulb"></i> Quick Solutions</h3>
                        </div>
                        <div class="support-card-body">
                            <div class="solution-item">
                                <h4><i class="fas fa-question-circle"></i> Common Issues</h4>
                                <ul class="solution-list">
                                    <li><a href="#">Payment not processed</a></li>
                                    <li><a href="#">Download problems</a></li>
                                    <li><a href="#">Account verification</a></li>
                                    <li><a href="#">Password reset</a></li>
                                </ul>
                            </div>
                            
                            <div class="solution-item">
                                <h4><i class="fas fa-book"></i> Knowledge Base</h4>
                                <ul class="solution-list">
                                    <li><a href="#">Getting started guide</a></li>
                                    <li><a href="#">Troubleshooting</a></li>
                                    <li><a href="#">FAQ</a></li>
                                </ul>
                            </div>
                            
                            <div class="support-info">
                                <h4><i class="fas fa-info-circle"></i> Support Information</h4>
                                <p>Our average response time is <strong>12-24 hours</strong>.</p>
                                <p>For urgent issues, please mention "URGENT" in your ticket.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- New Ticket Modal -->
            <div class="modal" id="newTicketModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-plus"></i> Create New Ticket</h3>
                        <button class="modal-close" id="modalClose">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="ticketForm" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="create_ticket" value="1">
                            <div class="form-group">
                                <label for="ticket-subject">Subject</label>
                                <input type="text" id="ticket-subject" name="subject" placeholder="Briefly describe your issue" required>
                            </div>
                            <div class="form-group">
                                <label for="ticket-category">Category</label>
                                <select id="ticket-category" name="category" required>
                                    <option value="">Select a category</option>
                                    <option value="billing">Billing/Payment</option>
                                    <option value="account">Account Issues</option>
                                    <option value="technical">Technical Support</option>
                                    <option value="product">Product Questions</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="ticket-message">Message</label>
                                <textarea id="ticket-message" name="message" rows="6" placeholder="Describe your issue in detail..." required></textarea>
                            </div>
                            <div class="form-group">
                            <label for="ticket-attachments">Attachments (Optional)</label>
                            <input type="file" id="ticket-attachments" name="ticket-attachments[]" multiple>
                            <small>Max 5MB per file (images, PDFs, docs)</small>
                        </div>
                            <div class="form-actions">
                                <button type="button" class="btn-secondary" id="cancelTicket">Cancel</button>
                                <button type="submit" class="btn-primary">Submit Ticket</button>
                            </div>
                        </form>
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
                        <!-- Content will be loaded dynamically here -->
                        <div class="loading-state">
                            <i class="fas fa-spinner fa-spin"></i> Loading ticket details...
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <footer class="main-footer">
            <p>&copy; <?= date('Y') ?> RLBMODS. All rights reserved.</p>
        </footer>
    </main>

    <script src="js/support.js"></script>
</body>
</html>