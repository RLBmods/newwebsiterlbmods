<?php
// Enable output buffering
ob_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once './includes/session.php';
require_once './includes/functions.php';
require_once './db/connection.php';
require_once './includes/logging.php';
require_once 'includes/get_user_info.php';

// Ensure the user is authenticated
requireAuth();

// Ensure the user is allowed accessing the page
requireMember();

// Check if the user is banned
// checkIfBanned();

// Fetch user balance
$user_id = $_SESSION['user_id'];
$balance_query = "SELECT balance FROM usertable WHERE id = ?";
$stmt = $con->prepare($balance_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$balance_result = $stmt->get_result();
$user_data = $balance_result->fetch_assoc();
$user_balance = $user_data['balance'] ?? 0;

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name;?> • Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/shop.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.11.2/css/all.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <script src="js/heartbeat.js" defer></script>
    <script src="js/notify.js" defer></script>
    <script src="js/shop.js" defer></script>
</head>
<body>
<div class="sidebar-overlay"></div>
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
                    <span class="breadcrumb-current">Shop</span>
                </div>
            </div>

            <!-- Balance Display -->
            <div class="header-right">
                <div class="user-balance-container">
                    <i class="fas fa-wallet"></i>
                    <span class="user-balance">$<?php echo number_format($user_balance, 2); ?></span>
                </div>
            </div>

            <!-- ========== Notifications Start ========== -->
            <?php include_once('./blades/notify/notify.php'); ?>
            <!-- ========== Notifications Ends ========== -->
        </header>
        
        <div class="shop-wrapper">
            <!-- Hero Section -->
            <div class="shop-hero">
                <div class="shop-hero-content">
                    <div class="shop-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <h2>RLBMods Shop</h2>
                    <p class="shop-subtitle">Premium cheats for your favorite games</p>
                </div>
            </div>
            
            <!-- Products Grid -->
            <div class="products-grid">
                <!-- Products will be loaded dynamically via JavaScript -->
                <div class="loading-products">
                    <i class="fas fa-spinner fa-spin"></i> Loading products...
                </div>
            </div>

<!-- Add this after the Products Grid section -->
<div class="transaction-history-section">
    <div class="section-header">
        <h2><i class="fas fa-history"></i> Purchase History</h2>
        <button class="btn-refresh-transactions">
            <i class="fas fa-sync-alt"></i>
        </button>
    </div>
    
    <div class="transactions-container">
        <div class="transaction-header">
            <span></span>
            <span>Product</span>
            <span>License Key</span>
            <span>Duration</span>
            <span>Price</span>
            <span>Purchase Date</span>
        </div>
        
        <div class="transactions-list" id="purchaseHistoryList">
            <div class="transaction-loading">
                <i class="fas fa-spinner fa-spin"></i> Loading purchase history...
            </div>
        </div>
    </div>
    
    <div class="pagination-container">
        <div class="pagination" id="purchaseHistoryPagination">
            <!-- Pagination will be loaded here by JavaScript -->
        </div>
    </div>
</div>

        </div>
        
        <!-- Product Modal -->
        <div class="product-modal-overlay">
            <div class="product-modal">
                <button class="modal-close-btn">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="modal-content">
                    <div class="modal-left">
                        <div class="modal-image">
                            <img id="modal-product-image" src="" alt="">
                        </div>
                    </div>
                    
                    <div class="modal-right">
                        <h2 id="modal-product-title"></h2>
                        <div class="modal-price">
                            <span id="modal-current-price" class="current-price"></span>
                            <span id="modal-original-price" class="original-price"></span>
                        </div>
                        <div class="modal-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                            <span class="rating-count"></span>
                        </div>
                        
                        <div class="product-description">
                            <h3>Description</h3>
                            <p id="modal-product-description"></p>
                        </div>
                        
                        <div class="product-features">
                            <h3>Features</h3>
                            <ul id="modal-product-features">
                                <!-- Features will be loaded dynamically -->
                            </ul>
                        </div>
                        
                        <div class="duration-selector">
                            <h3>Select Duration</h3>
                            <div class="duration-options">
                                <button class="duration-option" data-duration="daily">
                                    <span class="duration">Daily</span>
                                    <span class="price"></span>
                                </button>
                                <button class="duration-option" data-duration="weekly">
                                    <span class="duration">Weekly</span>
                                    <span class="price"></span>
                                </button>
                                <button class="duration-option" data-duration="monthly">
                                    <span class="duration">Monthly</span>
                                    <span class="price"></span>
                                </button>
                                <button class="duration-option" data-duration="lifetime">
                                    <span class="duration">Lifetime</span>
                                    <span class="price"></span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="modal-actions">
                            <button class="btn-buy-now">
                                <i class="fas fa-bolt"></i> Buy Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- License Modal (will be shown after purchase) -->
        <div class="license-modal-overlay" style="display: none;">
            <div class="license-modal">
                <button class="modal-close-btn">&times;</button>
                <h2>Purchase Complete</h2>
                <div class="license-details">
                    <p>Your license key:</p>
                    <div class="license-key-container">
                        <input type="text" id="license-key-display" readonly class="license-key-input">
                        <button class="copy-license-btn"><i class="fas fa-copy"></i></button>
                    </div>
                    <p class="expiry-info" id="license-expiry-info"></p>
                </div>
                <div class="modal-actions">
                    <button class="btn-primary btn-modal-close">Close</button>
                </div>
            </div>
        </div>
        
    <!-- ========== Footer Start ========== -->
    <?php include_once('./blades/footer/footer.php'); ?>
    <!-- ========== Footer Ends ========== -->
    </main>
</body>
</html>