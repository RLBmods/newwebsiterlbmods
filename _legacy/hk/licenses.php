<?php
ob_start();
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

// Fetch products
$query = "SELECT * FROM products WHERE visibility = 1 AND reseller_can_sell = 1";
$result = mysqli_query($con, $query);

if (!$result) {
    die("Error fetching products: " . mysqli_error($con));
}
$products = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get current user balance
$user_id = $_SESSION['user_id'];
$user_query = "SELECT balance FROM usertable WHERE id = ?";
$stmt = $con->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_balance = $user_data['balance'] ?? 0;

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name; ?> • Manage Licenses</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/resellerportal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.11.2/css/all.css">
    <link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
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
                    <span class="breadcrumb-current">Manage Licenses</span>
                </div>
            </div>

        <!-- ========== Left Sidebar Start ========== -->
        <?php include_once('../blades/notify/notify.php'); ?>
        <!-- ========== Left Sidebar Ends ========== -->

        </header>

        <div class="content-area-wrapper">
            <!-- Hero Banner Section -->
            <div class="reseller-hero gaming-hero">
                <div class="hero-overlay"></div>
                <div class="reseller-hero-content">
                    <div class="reseller-icon">
                    <i class="fas fa-key"></i>
                    </div>
                    <h1>License Management</h1>
                    <p class="reseller-subtitle">Create and manage product licenses</p>
                </div>
            </div>
            
            <!-- Product Tabs with Search Box -->
            <div class="product-tabs-container">
                <div class="product-tabs">
                    <?php foreach ($products as $index => $product): ?>
                        <button class="tab-btn <?= $index === 0 ? 'active' : '' ?>" 
                                data-product="<?= htmlspecialchars($product['name']) ?>"
                                data-product-data='<?= htmlspecialchars(json_encode([
                                    'name' => $product['name'],
                                    'type' => $product['type'],
                                    'daily_price' => $product['daily_price'],
                                    'weekly_price' => $product['weekly_price'],
                                    'monthly_price' => $product['monthly_price'],
                                    'lifetime_price' => $product['lifetime_price'],
                                    // Include other necessary fields
                                ]), ENT_QUOTES, 'UTF-8') ?>'>
                            <?= htmlspecialchars($product['name']) ?>
                        </button>
                    <?php endforeach; ?>
                    
                    <div class="tabs-search-container">
                        <div class="tabs-search-box">
                            <input type="text" id="searchInput" placeholder="Search licenses...">
                            <button id="searchBtn"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Two Column Layout -->
            <div class="reseller-columns">
                <!-- Left Column - License Table -->
                <div class="license-column">
                    
                    <!-- License Table -->
                    <div class="license-table-container">
                        <table class="license-table">
                            <thead>
                                <tr>
                                    <th>License</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Generated By</th>
                                    <th>Generation Date</th>
                                    <th>Activation Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-center">Select a product to view licenses</td>
                                </tr>
                            </tbody>
                        </table>
                        <!-- Add this right after the license table -->
<div class="pagination-controls">
    <button class="pagination-btn" id="prevPageBtn" disabled><i class="fas fa-chevron-left"></i></button>
    <span class="page-info">Page 1 of 1</span>
    <button class="pagination-btn" id="nextPageBtn" disabled><i class="fas fa-chevron-right"></i></button>
</div>
                    </div>
                </div>
                
                <!-- Right Column - Balance and Actions -->
                <div class="actions-column">
                    
                    <!-- License Actions Panel -->
                    <div class="license-actions-panel">
                        <div class="action-tabs">
                            <button class="action-tab-btn active" data-tab="create">Create License</button>
                            <button class="action-tab-btn" data-tab="reset">Reset HWID</button>
                        </div>
                        
                        <!-- Create License Form -->
                        <div class="action-tab-content active" id="create-tab">
                            <div class="form-group">
                                <label>Duration</label>
                                <select class="duration-select" id="duration_type">
                                    <option value="">Select Duration</option>
                                    <option value="1">Daily</option>
                                    <option value="7">Weekly</option>
                                    <option value="30">Monthly</option>
                                    <option value="9999">Lifetime</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Amount</label>
                                <input type="number" min="1" max="50" value="1" class="license-amount" id="count">
                            </div>
                            <button class="btn-primary btn-create-license" id="createLicenseBtn">
                                <i class="fas fa-plus"></i> Create License
                            </button>
                        </div>
                        
                        <!-- Reset HWID Form -->
                        <div class="action-tab-content" id="reset-tab">
                            <div class="form-group">
                                <label>Enter License</label>
                                <input type="text" placeholder="License Key" class="license-input" id="licenseKey">
                            </div>
                            <button class="btn-primary btn-reset-hwid" id="resetHwidBtn">
                                <i class="fas fa-sync-alt"></i> Reset HWID
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- License Creation Modal -->
        <div class="modal-overlay" id="licenseModal">
            <div class="modal-content license-modal">
                <div class="modal-header">
                    <h3>License Created</h3>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="licenseDetailsContainer">
                        <!-- License details will be inserted here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-primary btn-modal-close">Close</button>
                </div>
            </div>
        </div>
        
        <!-- Purchase Confirmation Modal -->
        <div class="modal-overlay" id="confirmPurchaseModal">
            <div class="modal-content confirm-modal">
                <div class="modal-header">
                    <h3>Confirm Purchase</h3>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="confirm-details">
                        <p>You are about to purchase:</p>
                        <div class="detail-row">
                            <span class="detail-label">Product:</span>
                            <span class="detail-value" id="confirmProductName"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Duration:</span>
                            <span class="detail-value" id="confirmDuration"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Quantity:</span>
                            <span class="detail-value" id="confirmQuantity"></span>
                        </div>
                        <div class="detail-row total-row">
                            <span class="detail-label">Total Cost:</span>
                            <span class="detail-value" id="confirmTotalCost"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-secondary btn-modal-cancel">Cancel</button>
                    <button class="btn-primary btn-confirm-purchase">Confirm Purchase</button>
                </div>
            </div>
        </div>
        
        <footer class="main-footer">
            <p>
                &copy; 2023 RLBMODS. All rights reserved. | 
                <span class="badge">
                    <i class="fas fa-code"> </i>  CompileCrew
                </span>
            </p>
        </footer>
    </main>

    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            const notyf = new Notyf({
                duration: 3000,
                position: { x: 'right', y: 'bottom' },
                dismissible: true,
                types: [
                    {
                        type: 'success',
                        background: '#4CAF50',
                        icon: {
                            className: 'fas fa-check-circle',
                            tagName: 'i',
                            color: '#fff'
                        }
                    },
                    {
                        type: 'error',
                        background: '#F44336',
                        icon: {
                            className: 'fas fa-exclamation-circle',
                            tagName: 'i',
                            color: '#fff'
                        }
                    },
                    {
                        type: 'warning',
                        background: '#FF9800',
                        icon: {
                            className: 'fas fa-exclamation-triangle',
                            tagName: 'i',
                            color: '#fff'
                        }
                    }
                ]
            });

            let currentProduct = "";
            let currentProductData = {};
            let userBalance = <?= $user_balance ?? 0 ?>;
            let allLicenses = [];
            const licensesPerPage = 7;
            let filteredLicenses = [];
            let currentPage = 1;
            let currentSearchTerm = '';

            // Initialize with first product if available
            const firstTab = document.querySelector('.tab-btn.active');
            if (firstTab) {
                currentProduct = firstTab.getAttribute('data-product');
                currentProductData = JSON.parse(firstTab.getAttribute('data-product-data'));
                fetchLicenses(currentProduct);
            }

            // Product Tab Switching
            document.querySelectorAll('.tab-btn').forEach(tab => {
    tab.addEventListener('click', function() {
        currentProduct = this.getAttribute('data-product');
        currentProductData = JSON.parse(this.getAttribute('data-product-data'));
        
        // Reset search when switching products
        document.getElementById('searchInput').value = '';
        currentSearchTerm = '';
        
        // Update active tab
        document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        // Load licenses for selected product
        fetchLicenses(currentProduct);
        
        // Reset form
        document.getElementById('duration_type').value = '';
        document.getElementById('count').value = '1';
        document.getElementById('total_cost').textContent = '$0.00';
    });
});

            // Action Tab Switching
            document.querySelectorAll('.action-tab-btn').forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    document.querySelectorAll('.action-tab-btn').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.action-tab-content').forEach(c => c.classList.remove('active'));
                    
                    this.classList.add('active');
                    document.getElementById(`${tabId}-tab`).classList.add('active');
                });
            });

            // Calculate License Cost
            function calculateCost() {
                const durationType = document.getElementById('duration_type').value;
                const count = parseInt(document.getElementById('count').value) || 0;
                
                if (!durationType || !currentProductData) {
                    document.getElementById('total_cost').textContent = '$0.00';
                    return;
                }
                
                let pricePerUnit = 0;
                
                switch(durationType) {
                    case '1': 
                        pricePerUnit = parseFloat(currentProductData.daily_price) || 0; 
                        break;
                    case '7': 
                        pricePerUnit = parseFloat(currentProductData.weekly_price) || 0; 
                        break;
                    case '30': 
                        pricePerUnit = parseFloat(currentProductData.monthly_price) || 0; 
                        break;
                    case '9999': 
                        pricePerUnit = parseFloat(currentProductData.lifetime_price) || 0; 
                        break;
                    default: 
                        pricePerUnit = 0;
                }
                
                const totalCost = (pricePerUnit * count).toFixed(2);
                document.getElementById('total_cost').textContent = `$${totalCost}`;
            }
            
            document.getElementById('duration_type').addEventListener('change', calculateCost);
            document.getElementById('count').addEventListener('input', function() {
                // Clamp value between min and max
                if (this.value > 50) this.value = 50;
                if (this.value < 1) this.value = 1;
                calculateCost();
            });

            // Fetch licenses from API
            function fetchLicenses(productName) {
            const licenseTableBody = document.querySelector('.license-table tbody');
            licenseTableBody.innerHTML = '<tr><td colspan="6" class="text-center">Loading licenses...</td></tr>';
            
            $.ajax({
                url: '../api/hk/licenses/fetch-licenses.php',
                method: 'GET',
                data: { productName }, // Remove searchTerm from here
                success: function(response) {
                    if (response.success && response.licenses && response.licenses.length > 0) {
                        allLicenses = response.licenses;
                        filteredLicenses = [...allLicenses]; // Make a copy for filtering
                        currentPage = 1; // Reset to first page
                        updateLicensesDisplay();
                    } else {
                        allLicenses = [];
                        filteredLicenses = [];
                        licenseTableBody.innerHTML = '<tr><td colspan="6" class="text-center">No licenses available</td></tr>';
                        updatePaginationControls();
                        if (!response.success) {
                            notyf.error(response.message || 'Failed to fetch licenses');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching licenses:', error);
                    notyf.error('Error fetching licenses');
                    licenseTableBody.innerHTML = '<tr><td colspan="6" class="text-center">Error loading licenses</td></tr>';
                }
            });
        }

        // New function to update the displayed licenses based on current page
        function updateLicensesDisplay() {
    const licenseTableBody = document.querySelector('.license-table tbody');
    licenseTableBody.innerHTML = '';
    
    if (filteredLicenses.length === 0) {
        // Show appropriate message based on whether we're searching
        const message = currentSearchTerm ? 
            'No licenses match your search criteria' : 
            'No licenses available';
        licenseTableBody.innerHTML = `<tr><td colspan="6" class="text-center">${message}</td></tr>`;
        updatePaginationControls();
        return;
    }
    
    // Calculate which licenses to show
    const startIndex = (currentPage - 1) * licensesPerPage;
    const endIndex = startIndex + licensesPerPage;
    const licensesToShow = filteredLicenses.slice(startIndex, endIndex);
    
    // Render the licenses
    licensesToShow.forEach(license => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${highlightSearchTerm(license.key || 'N/A')}</td>
            <td>${highlightSearchTerm(license.duration || 'N/A')}</td>
            <td><span class="status-badge ${license.status.toLowerCase()}">${highlightSearchTerm(license.status || 'N/A')}</span></td>
            <td>${highlightSearchTerm(license.genby || 'N/A')}</td>
            <td>${highlightSearchTerm(license.gendate || 'N/A')}</td>
            <td>${highlightSearchTerm(license.activation_date || 'None')}</td>
        `;
        licenseTableBody.appendChild(row);
    });
    
    updatePaginationControls();
}

function highlightSearchTerm(text) {
    if (!currentSearchTerm || !text) return text;
    
    const regex = new RegExp(`(${currentSearchTerm})`, 'gi');
    return text.toString().replace(regex, '<span class="search-highlight">$1</span>');
}

// New function to update pagination controls
function updatePaginationControls() {
    const totalPages = Math.ceil(filteredLicenses.length / licensesPerPage);
    document.getElementById('prevPageBtn').disabled = currentPage <= 1;
    document.getElementById('nextPageBtn').disabled = currentPage >= totalPages;
    document.querySelector('.page-info').textContent = `Page ${currentPage} of ${totalPages}`;
}

// Add event listeners for pagination buttons
document.getElementById('prevPageBtn').addEventListener('click', function() {
    if (currentPage > 1) {
        currentPage--;
        updateLicensesDisplay();
    }
});

document.getElementById('nextPageBtn').addEventListener('click', function() {
    const totalPages = Math.ceil(filteredLicenses.length / licensesPerPage);
    if (currentPage < totalPages) {
        currentPage++;
        updateLicensesDisplay();
    }
});

// Modify your search functionality to reset to page 1
document.getElementById('searchBtn').addEventListener('click', function() {
    const searchTerm = document.getElementById('searchInput').value.trim().toLowerCase();
    currentSearchTerm = searchTerm;
    currentPage = 1;
    
    if (searchTerm === '') {
        filteredLicenses = allLicenses;
    } else {
        filteredLicenses = allLicenses.filter(license => {
            return (
                (license.key && license.key.toLowerCase().includes(searchTerm)) ||
                (license.status && license.status.toLowerCase().includes(searchTerm)) ||
                (license.genby && license.genby.toLowerCase().includes(searchTerm)) ||
                (license.gendate && license.gendate.toLowerCase().includes(searchTerm)) ||
                (license.activation_date && license.activation_date.toLowerCase().includes(searchTerm))
            );
        });
    }
    
    updateLicensesDisplay();
});

document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('searchBtn').click();
    }
});

            // Render licenses in table
            function renderLicenses(licenses) {
                const licenseTableBody = document.querySelector('.license-table tbody');
                licenseTableBody.innerHTML = '';
                
                if (licenses.length === 0) {
                    licenseTableBody.innerHTML = '<tr><td colspan="6" class="text-center">No licenses found</td></tr>';
                    return;
                }
                
                licensesToShow.forEach(license => {
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>${highlightSearchTerm(license.key || 'N/A')}</td>
        <td>${highlightSearchTerm(license.duration || 'N/A')}</td>
        <td><span class="status-badge ${license.status.toLowerCase()}">${highlightSearchTerm(license.status || 'N/A')}</span></td>
        <td>${highlightSearchTerm(license.genby || 'N/A')}</td>
        <td>${highlightSearchTerm(license.gendate || 'N/A')}</td>
        <td>${highlightSearchTerm(license.activation_date || 'None')}</td>
    `;
    licenseTableBody.appendChild(row);
});
            }

            // Create License - Show confirmation modal first
// Update the create license button handler
document.getElementById('createLicenseBtn').addEventListener('click', function() {
    const durationType = document.getElementById('duration_type').value;
    const durationDays = durationType;
    const count = document.getElementById('count').value;
    
    if (!currentProduct) {
        notyf.error('Please select a product');
        return;
    }
    
    if (!durationType || !count || count < 1) {
        notyf.error('Please select duration and amount');
        return;
    }
    
    // Show loading state
    const createBtn = document.getElementById('createLicenseBtn');
    createBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    createBtn.disabled = true;
    
    $.ajax({
        url: '../api/hk/licenses/create-license.php',
        method: 'POST',
        data: { 
            productName: currentProduct, 
            duration: durationDays, 
            count,
            durationType
        },
// Update the success handler in the create license AJAX call
success: function(response) {
    createBtn.innerHTML = '<i class="fas fa-plus"></i> Create License';
    createBtn.disabled = false;
    
    if (response.success) {
        notyf.success(response.message);
        
        const licenseModal = document.getElementById('licenseModal');
        const licenseDetailsContainer = document.getElementById('licenseDetailsContainer');
        
        // Clear previous content
        licenseDetailsContainer.innerHTML = '';
        
        // Create a textarea for easy copying
        const textarea = document.createElement('textarea');
        textarea.className = 'license-copy-area';
        textarea.readOnly = true;
        textarea.rows = Math.min(10, response.licenses.length + 2); // Dynamic height
        textarea.style.width = '100%';
        textarea.style.marginBottom = '15px';
        textarea.style.fontFamily = 'monospace';
        textarea.style.textAlign = 'center';
        textarea.style.fontSize = '16px';
        
        // Build the license text (keys only)
        let licenseText = response.licenses.map(license => license.key).join('\n');
        textarea.value = licenseText;
        licenseDetailsContainer.appendChild(textarea);
        
        // Add copy button
        const copyButton = document.createElement('button');
        copyButton.className = 'btn-primary btn-copy-all';
        copyButton.innerHTML = '<i class="fas fa-copy"></i> Copy All License Keys';
        copyButton.addEventListener('click', function() {
            textarea.select();
            document.execCommand('copy');
            
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check"></i> Copied!';
            setTimeout(() => {
                this.innerHTML = originalText;
            }, 2000);
        });
        
        licenseDetailsContainer.appendChild(copyButton);
        licenseModal.style.display = 'flex';
        fetchLicenses(currentProduct);
    } else {
        notyf.error(response.message || 'Failed to create license');
    }
},
        error: function() {
            createBtn.innerHTML = '<i class="fas fa-plus"></i> Create License';
            createBtn.disabled = false;
            notyf.error('Error creating license');
        }
    });
});

            // Confirm Purchase button handler
            document.querySelector('.btn-confirm-purchase').addEventListener('click', function() {
                const durationType = document.getElementById('duration_type').value;
                const durationDays = durationType;
                const count = document.getElementById('count').value;
                const totalCost = parseFloat(document.getElementById('total_cost').textContent.replace('$', '')) || 0;
                
                // Close the confirmation modal
                document.getElementById('confirmPurchaseModal').style.display = 'none';
                
                // Show loading state
                const createBtn = document.getElementById('createLicenseBtn');
                createBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                createBtn.disabled = true;
                
                $.ajax({
                    url: '../api/hk/licenses/create-license.php',
                    method: 'POST',
                    data: { 
                        productName: currentProduct, 
                        duration: durationDays, 
                        count,
                        durationType,
                        totalCost 
                    },
                    success: function(response) {
                        createBtn.innerHTML = '<i class="fas fa-plus"></i> Create License';
                        createBtn.disabled = false;
                        
                        if (response.success) {
                            notyf.success(response.message);
                            userBalance = response.newBalance;
                            document.querySelector('.balance-amount').textContent = '$' + response.newBalance.toFixed(2);
                            
                            const licenseModal = document.getElementById('licenseModal');
                            const singleLicenseContainer = document.getElementById('singleLicenseContainer');
                            const multipleLicensesContainer = document.getElementById('multipleLicensesContainer');
                            const singleLicenseValue = document.getElementById('singleLicenseValue');
                            
                            // Clear previous content
                            multipleLicensesContainer.innerHTML = '';
                            
                            if (response.key) {
                                // Single license
                                singleLicenseContainer.style.display = 'flex';
                                multipleLicensesContainer.style.display = 'none';
                                singleLicenseValue.textContent = response.key;
                            } else if (response.keys && response.keys.length > 0) {
                                // Multiple licenses
                                singleLicenseContainer.style.display = 'none';
                                multipleLicensesContainer.style.display = 'block';
                                
                                response.keys.forEach(licenseKey => {
                                    const licenseItem = document.createElement('div');
                                    licenseItem.className = 'license-item';
                                    licenseItem.innerHTML = `
                                        <span>${licenseKey}</span>
                                        <button class="copy-license-btn">
                                            <i class="fas fa-copy"></i> Copy
                                        </button>
                                    `;
                                    multipleLicensesContainer.appendChild(licenseItem);
                                });
                                
                                // Add copy all button
                                const copyAllBtn = document.createElement('button');
                                copyAllBtn.className = 'btn-secondary btn-copy-all';
                                copyAllBtn.innerHTML = '<i class="fas fa-copy"></i> Copy All Licenses';
                                multipleLicensesContainer.appendChild(copyAllBtn);
                                
                                // Add event listener for the new copy all button
                                copyAllBtn.addEventListener('click', function() {
                                    const licenseText = response.keys.join('\n');
                                    navigator.clipboard.writeText(licenseText)
                                        .then(() => {
                                            const originalText = this.innerHTML;
                                            this.innerHTML = '<i class="fas fa-check"></i> Copied All!';
                                            setTimeout(() => {
                                                this.innerHTML = originalText;
                                            }, 2000);
                                        });
                                });
                            }
                            
                            licenseModal.style.display = 'flex';
                            fetchLicenses(currentProduct);
                        } else {
                            notyf.error(response.message || 'Failed to create license');
                        }
                    },
                    error: function() {
                        createBtn.innerHTML = '<i class="fas fa-plus"></i> Create License';
                        createBtn.disabled = false;
                        notyf.error('Error creating license');
                    }
                });
            });

            // Cancel button handler
            document.querySelector('.btn-modal-cancel').addEventListener('click', function() {
                document.getElementById('confirmPurchaseModal').style.display = 'none';
            });

            // Close modal when clicking X or overlay
            document.querySelectorAll('#confirmPurchaseModal .close-modal, #confirmPurchaseModal').forEach(el => {
                el.addEventListener('click', function(e) {
                    if (e.target === this || e.target.classList.contains('close-modal')) {
                        document.getElementById('confirmPurchaseModal').style.display = 'none';
                    }
                });
            });

            // Reset HWID
            document.getElementById('resetHwidBtn').addEventListener('click', function() {
                const licenseKey = document.getElementById('licenseKey').value;
                
                if (!currentProduct || !licenseKey) {
                    notyf.error('Please select product and enter license key');
                    return;
                }
                
                $.ajax({
                    url: '../api/hk/licenses/reset-hwid.php',
                    method: 'POST',
                    data: { productName: currentProduct, licenseKey },
                    success: function(response) {
                        if (response.success) {
                            notyf.success(response.message);
                            fetchLicenses(currentProduct);
                        } else {
                            notyf.error(response.message || 'Failed to reset HWID');
                        }
                    },
                    error: function() {
                        notyf.error('Error resetting HWID');
                    }
                });
            });

            // Add this function to handle the search logic
            function performSearch() {
    const searchTerm = document.getElementById('searchInput').value.trim().toLowerCase();
    currentSearchTerm = searchTerm;
    currentPage = 1; // Reset to first page when searching
    
    if (searchTerm === '') {
        // If search is empty, show all licenses
        filteredLicenses = [...allLicenses];
    } else {
        // Filter licenses based on search term
        filteredLicenses = allLicenses.filter(license => {
            // Search in all relevant fields
            return (
                (license.key && license.key.toLowerCase().includes(searchTerm)) ||
                (license.status && license.status.toLowerCase().includes(searchTerm)) ||
                (license.genby && license.genby.toLowerCase().includes(searchTerm)) ||
                (license.gendate && license.gendate.toLowerCase().includes(searchTerm)) ||
                (license.activation_date && license.activation_date.toLowerCase().includes(searchTerm)) ||
                (license.duration && license.duration.toLowerCase().includes(searchTerm))
            );
        });
    }
    
    updateLicensesDisplay();
}

// Update search event listeners
document.getElementById('searchBtn').addEventListener('click', performSearch);

document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        performSearch();
    }
});

// Clear search when input is empty
document.getElementById('searchInput').addEventListener('input', function() {
    if (this.value === '') {
        performSearch();
    }
});

            // Modal Controls
            document.querySelector('.close-modal').addEventListener('click', function() {
                document.getElementById('licenseModal').style.display = 'none';
            });
            
            document.querySelector('.btn-modal-close').addEventListener('click', function() {
                document.getElementById('licenseModal').style.display = 'none';
            });
            
            window.addEventListener('click', function(event) {
                if (event.target === document.getElementById('licenseModal')) {
                    document.getElementById('licenseModal').style.display = 'none';
                }
                if (event.target === document.getElementById('confirmPurchaseModal')) {
                    document.getElementById('confirmPurchaseModal').style.display = 'none';
                }
            });
            
            // Copy License (delegated event for dynamic elements)
            document.addEventListener('click', function(e) {
                if (e.target.closest('.copy-license-btn')) {
                    const btn = e.target.closest('.copy-license-btn');
                    let licenseValue = '';
                    
                    if (btn.closest('#singleLicenseContainer')) {
                        licenseValue = document.getElementById('singleLicenseValue').textContent;
                    } else if (btn.closest('.license-item')) {
                        licenseValue = btn.closest('.license-item').querySelector('span').textContent;
                    }
                    
                    navigator.clipboard.writeText(licenseValue.trim())
                        .then(() => {
                            const originalText = btn.innerHTML;
                            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                            setTimeout(() => {
                                btn.innerHTML = originalText;
                            }, 2000);
                        })
                        .catch(err => {
                            console.error('Failed to copy text: ', err);
                        });
                }
            });
        });
    </script>
</body>
</html>