<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Required files
require_once 'includes/session.php';
require_once 'includes/get_user_info.php';
include 'db/connection.php';

requireAuth(); // Ensure user is authenticated

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    switch ($action) {
        case 'get_giveaways':
            $filter = $_POST['filter'] ?? 'all';
            $search = $_POST['search'] ?? '';
            
            // Base query
            $query = "SELECT g.*, 
                     (SELECT COUNT(*) FROM giveaway_entries WHERE giveaway_id = g.id) as entries,
                     w.username as winner_name,
                     w.draw_date as winner_date
                     FROM giveaways g
                     LEFT JOIN giveaway_winners w ON g.id = w.giveaway_id";
            
            // Apply filters
            $where = [];
            $params = [];
            $types = '';
            
            if ($filter !== 'all') {
                $now = date('Y-m-d H:i:s');
                if ($filter === 'active') {
                    $where[] = "g.start_date <= ? AND g.end_date >= ?";
                    $params[] = $now;
                    $params[] = $now;
                    $types .= 'ss';
                } elseif ($filter === 'upcoming') {
                    $where[] = "g.start_date > ?";
                    $params[] = $now;
                    $types .= 's';
                } elseif ($filter === 'ended') {
                    $where[] = "g.end_date < ?";
                    $params[] = $now;
                    $types .= 's';
                }
            }
            
            if (!empty($search)) {
                $where[] = "g.title LIKE ?";
                $params[] = "%$search%";
                $types .= 's';
            }
            
            if (!empty($where)) {
                $query .= " WHERE " . implode(" AND ", $where);
            }
            
            $query .= " ORDER BY 
                      CASE 
                        WHEN g.start_date > NOW() THEN 1
                        WHEN g.end_date >= NOW() THEN 2
                        ELSE 3
                      END,
                      g.start_date ASC";
            
            // Prepare and execute
            $stmt = $con->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $giveaways = [];
            while ($row = $result->fetch_assoc()) {
                // Check if user has entered
                $entry_stmt = $con->prepare("SELECT 1 FROM giveaway_entries WHERE giveaway_id = ? AND user_id = ?");
                $entry_stmt->bind_param("ii", $row['id'], $user_id);
                $entry_stmt->execute();
                $row['has_entered'] = $entry_stmt->get_result()->num_rows > 0;
                $entry_stmt->close();
                
                // Get requirements
                $req_stmt = $con->prepare("SELECT requirement FROM giveaway_requirements WHERE giveaway_id = ?");
                $req_stmt->bind_param("i", $row['id']);
                $req_stmt->execute();
                $req_result = $req_stmt->get_result();
                $row['requirements'] = [];
                while ($req = $req_result->fetch_assoc()) {
                    $row['requirements'][] = $req['requirement'];
                }
                $req_stmt->close();
                
                $giveaways[] = $row;
            }
            
            echo json_encode(['success' => true, 'giveaways' => $giveaways]);
            break;
            
        case 'enter_giveaway':
            $giveaway_id = (int)$_POST['giveaway_id'];
            
            // Verify giveaway exists and is active
            $stmt = $con->prepare("SELECT 1 FROM giveaways WHERE id = ? AND start_date <= NOW() AND end_date >= NOW()");
            $stmt->bind_param("i", $giveaway_id);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows === 0) {
                echo json_encode(['success' => false, 'error' => 'Giveaway not available for entry']);
                break;
            }
            
            // Check if already entered
            $check_stmt = $con->prepare("SELECT 1 FROM giveaway_entries WHERE giveaway_id = ? AND user_id = ?");
            $check_stmt->bind_param("ii", $giveaway_id, $user_id);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'error' => 'You have already entered this giveaway']);
                break;
            }
            
            // Insert entry
            $insert_stmt = $con->prepare("INSERT INTO giveaway_entries (giveaway_id, user_id, entry_date) VALUES (?, ?, NOW())");
            $insert_stmt->bind_param("ii", $giveaway_id, $user_id);
            
            if ($insert_stmt->execute()) {
                // Update entries count
                $con->query("UPDATE giveaways SET entries = entries + 1 WHERE id = $giveaway_id");
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to enter giveaway']);
            }
            break;
            
        case 'create_giveaway':
            // Verify admin privileges
            if (!isAdmin($_SESSION['user_id'])) {
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                break;
            }
            
            $title = $_POST['title'];
            $description = $_POST['description'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $image_url = $_POST['image_url'] ?: 'https://placehold.co/800x800';
            $requirements = json_decode($_POST['requirements'], true) ?: [];
            
            // Validate dates
            if (strtotime($start_date) >= strtotime($end_date)) {
                echo json_encode(['success' => false, 'error' => 'End date must be after start date']);
                break;
            }
            
            // Insert giveaway
            $stmt = $con->prepare("INSERT INTO giveaways (title, description, image_url, start_date, end_date, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $title, $description, $image_url, $start_date, $end_date, $user_id);
            
            if ($stmt->execute()) {
                $giveaway_id = $con->insert_id;
                
                // Insert requirements
                if (!empty($requirements)) {
                    $req_stmt = $con->prepare("INSERT INTO giveaway_requirements (giveaway_id, requirement) VALUES (?, ?)");
                    foreach ($requirements as $req) {
                        if (!empty(trim($req))) {
                            $req_stmt->bind_param("is", $giveaway_id, $req);
                            $req_stmt->execute();
                        }
                    }
                    $req_stmt->close();
                }
                
                echo json_encode(['success' => true, 'giveaway_id' => $giveaway_id]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create giveaway']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name; ?> • Giveaways</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/giveaway.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.11.2/css/all.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="js/heartbeat.js" defer></script>
    <script src="js/notify.js" defer></script>
</head>
<body>
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
                    <span class="breadcrumb-current">Giveaway</span>
                </div>
            </div>

        <!-- ========== Left Sidebar Start ========== -->
        <?php include_once('./blades/notify/notify.php'); ?>
        <!-- ========== Left Sidebar Ends ========== -->

        </header>

        <div class="content-area-wrapper">
            <!-- Giveaway Hero Section -->
            <div class="giveaway-hero">
                <div class="banner-content">
                    <div class="giveaway-icon">
                        <i class="fas fa-gift"></i>
                    </div>
                    <h1>Current Giveaways</h1>
                    <p class="giveaway-subtitle">Participate and win amazing prizes!</p>
                </div>
            </div>
            
            <!-- Giveaway Filters -->
            <div class="giveaway-filters">
                <div class="filter-options">
                    <button class="filter-btn active" data-filter="all">All Giveaways</button>
                    <button class="filter-btn" data-filter="active">Active</button>
                    <button class="filter-btn" data-filter="upcoming">Upcoming</button>
                    <button class="filter-btn" data-filter="ended">Ended</button>
                </div>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search giveaways..." id="searchInput">
                </div>
            </div>
            
            <!-- Giveaways Grid -->
            <div class="giveaways-grid" id="giveawaysGrid">
                <!-- Giveaways will be loaded here by JavaScript -->
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Loading giveaways...</p>
                </div>
            </div>
            
            <!-- Create Giveaway Button (Admin Only) -->
            <?php if (isAdmin($_SESSION['user_id'])): ?>
            <button class="btn-create-giveaway" id="createGiveawayBtn">
                <i class="fas fa-plus"></i> Create New Giveaway
            </button>
            <?php endif; ?>
            
            <!-- Giveaway Modals -->
            <div class="modal-overlay" id="giveawayModal">
                <div class="modal-container-wrapper">
                    <!-- Left Modal - Giveaway Details -->
                    <div class="modal-container-left">
                        <div class="modal-header">
                            <h3>Giveaway Details</h3>
                            <button class="modal-close" onclick="closeAllModals()">&times;</button>
                        </div>
                        
                        <div class="modal-body">
                            <img src="" alt="Giveaway Prize" class="giveaway-prize-image" id="giveawayImage">
                            
                            <span class="giveaway-status-badge" id="giveawayStatusBadge">Active</span>
                            
                            <h2 id="giveawayTitle">Premium Mod Package</h2>
                            
                            <div class="giveaway-meta">
                                <div class="giveaway-meta-item">
                                    <div class="giveaway-meta-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div>
                                        <div class="meta-label">Entries</div>
                                        <div class="meta-value" id="entryCount">0</div>
                                    </div>
                                </div>
                                
                                <div class="giveaway-meta-item">
                                    <div class="giveaway-meta-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div>
                                        <div class="meta-label" id="timeLabel">Ends in</div>
                                        <div class="meta-value" id="endsIn">2 days 5 hours</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="giveaway-description" id="giveawayDescription">
                                Win our premium mod package worth $100! Includes all current and future mods for 1 year.
                            </div>
                            
                            <div class="modal-actions">
                                <button class="btn btn-modal-primary" id="enterGiveawayBtn">
                                    <i class="fas fa-ticket-alt"></i> Enter Giveaway
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Modal - Requirements/Winner -->
                    <div class="modal-container-right" id="rightModalContainer">
                        <div class="modal-header">
                            <h3 id="rightModalTitle">Requirements</h3>
                            <button class="modal-close" onclick="closeAllModals()">&times;</button>
                        </div>
                        
                        <div class="modal-body" id="rightModalContent">
                            <!-- Content will be dynamically filled with either requirements or winner info -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Create Giveaway Modal -->
            <div class="modal-overlay" id="createGiveawayModal">
                <div class="modal-container">
                    <div class="modal-header">
                        <h3>Create New Giveaway</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    
                    <div class="modal-body">
                        <form id="giveawayForm">
                            <div class="form-group">
                                <label for="giveawayName">Giveaway Title</label>
                                <input type="text" id="giveawayName" placeholder="Premium Mod Package" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="giveawayDesc">Description</label>
                                <textarea id="giveawayDesc" rows="4" placeholder="Describe the giveaway prize and details..." required></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="startDate">Start Date</label>
                                    <input type="datetime-local" id="startDate" required>
                                </div>
                                <div class="form-group">
                                    <label for="endDate">End Date</label>
                                    <input type="datetime-local" id="endDate" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="prizeImage">Prize Image URL</label>
                                <input type="url" id="prizeImage" placeholder="https://example.com/image.jpg">
                            </div>
                            
                            <div class="form-group">
                                <label>Requirements</label>
                                <div class="requirements-list" id="createRequirementsList">
                                    <div class="requirement-item">
                                        <input type="text" placeholder="Must be a registered user">
                                        <button type="button" class="btn-remove-requirement"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                                <button type="button" class="btn-add-requirement" id="addRequirementBtn">
                                    <i class="fas fa-plus"></i> Add Requirement
                                </button>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn-cancel" id="cancelGiveawayBtn">Cancel</button>
                                <button type="submit" class="btn-submit">Create Giveaway</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <footer class="main-footer">
            <p>
                &copy; <?php echo date('Y'); ?> RLBMODS. All rights reserved. | 
                <span class="badge">
                    <i class="fas fa-code"></i> CompileCrew
                </span>
            </p>
        </footer>
    </main>
    <script src="js/giveaway.js"></script>
</body>
</html>