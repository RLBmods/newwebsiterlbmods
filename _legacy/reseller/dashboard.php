<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/session.php';
require_once '../includes/get_user_info.php';
requireAuth();
requireReseller();

// Fetch products
$query = "SELECT * FROM products WHERE visibility = 1 AND reseller_can_sell = 1";
$result = mysqli_query($con, $query);
if (!$result) {
    die("Error fetching products: " . mysqli_error($con));
}
$products = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get current user balance
$user_id = $_SESSION['user_id'];
$user_query = "SELECT balance, discount_override FROM usertable WHERE id = ?";
$stmt = $con->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$user_balance = $user_data['balance'] ?? 0;

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseller Portal • RLB Mods</title>
    <link rel="stylesheet" href="https://panel.rlbmods.com/css/style.css">
    <link rel="stylesheet" href="https://panel.rlbmods.com/css/resellerportal.css">
    <!-- FontAwesome 6 for solid icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <style>
        .original-price { text-decoration: line-through; opacity: 0.5; margin-right: 8px; font-size: 0.9em; }
        .discounted-price { color: #4CAF50; font-weight: bold; }
        .discount-badge { background: #ff4757; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.75em; margin-left: 8px; vertical-align: middle; }
        
        /* Modal Styles */
        .created-license { background: rgba(255,255,255,0.05); padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 10px; border: 1px solid rgba(255,255,255,0.1); }
        .license-value { font-family: 'Courier New', monospace; font-size: 1.4em; color: #f1c40f; margin-bottom: 15px; letter-spacing: 1px; word-break: break-all; }
        .copy-license-btn { background: #3498db; color: white; border: none; padding: 8px 20px; border-radius: 5px; cursor: pointer; transition: 0.3s; }
        .license-item { display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.05); padding: 12px; border-radius: 6px; margin-bottom: 8px; border: 1px solid rgba(255,255,255,0.1); }
        
        .action-tab-content { display: none; }
        .action-tab-content.active { display: block; }

        /* Fixed Pagination Styles */
        .pagination-controls { display: flex; align-items: center; justify-content: center; gap: 20px; margin-top: 25px; padding: 15px; background: rgba(0,0,0,0.2); border-radius: 8px; }
        .pagination-btn { 
            border: 1px solid #34495e; 
            color: #fff; 
            width: 45px; 
            height: 45px; 
            border-radius: 6px; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            transition: all 0.2s ease;
        }
        .pagination-btn i { font-size: 18px; pointer-events: none; }
        .pagination-btn:hover:not(:disabled) { ; transform: translateY(-2px); }
        .pagination-btn:disabled { opacity: 0.2; cursor: not-allowed; }
        .page-info { font-family: 'Oxanium', sans-serif; font-size: 1rem; color: #fff; min-width: 120px; text-align: center; }
    </style>
</head>
<body>
    <?php include_once('../blades/sidebar/reseller-sidebar.php'); ?>
    
    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <div class="breadcrumbs">
                    <a href="/" class="breadcrumb-item"><i class="fas fa-home"></i></a>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-current">Dashboard</span>
                </div>
            </div>
            <?php include_once('../blades/notify/notify.php'); ?>
        </header>

        <div class="content-area-wrapper">
            <div class="reseller-hero gaming-hero">
                <div class="hero-overlay"></div>
                <div class="reseller-hero-content">
                    <div class="reseller-icon"><i class="fas fa-handshake"></i></div>
                    <h2>Reseller Portal</h2>
                    <p class="reseller-subtitle">Manage your licenses and customers</p>
                </div>
            </div>
            
            <div class="product-tabs-container">
                <div class="product-tabs">
                    <?php foreach ($products as $index => $product): ?>
                        <button class="tab-btn <?= $index === 0 ? 'active' : '' ?>" 
                                data-product="<?= htmlspecialchars($product['name']) ?>"
                                data-product-data='<?= htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8') ?>'>
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
            
            <div class="reseller-columns">
                <div class="license-column">
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
                                <tr><td colspan="6" class="text-center">Loading licenses...</td></tr>
                            </tbody>
                        </table>
                        
                        <!-- Pagination Section -->
                        <div class="pagination-controls">
                            <button class="pagination-btn" id="prevPageBtn" title="Previous Page">
                                <i class="fa-solid fa-chevron-left"></i>
                            </button>
                            <span class="page-info" id="pageNumberDisplay">Page 1 of 1</span>
                            <button class="pagination-btn" id="nextPageBtn" title="Next Page">
                                <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="actions-column">
                    <div class="balance-box">
                        <div class="balance-content">
                            <i class="fas fa-wallet"></i>
                            <div class="balance-info">
                                <span class="balance-label">Your Balance</span>
                                <span class="balance-amount" id="displayBalance">$<?= number_format($user_balance, 2) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="license-actions-panel">
                        <div class="action-tabs">
                            <button class="action-tab-btn active" data-tab="create">Create License</button>
                            <button class="action-tab-btn" data-tab="reset">Reset HWID</button>
                        </div>
                        
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
                            <div class="cost-display">
                                <span>Total Cost:</span>
                                <span class="cost-amount" id="total_cost">$0.00</span>
                            </div>
                            <button class="btn-primary btn-create-license" id="createLicenseBtn">
                                <i class="fas fa-plus"></i> Create License
                            </button>
                        </div>
                        
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

                    <div class="api-key-box">
                        <div class="api-key-header">
                            <i class="fas fa-key"></i>
                            <h3>Reseller API Key</h3>
                            <div class="api-key-buttons">
                                <button class="btn-regenerate-api" id="regenerateApiBtn"><i class="fas fa-sync-alt"></i> Regenerate</button>
                            </div>
                        </div>
                        <div class="api-key-content">
                            <div class="api-key-textarea-container">
                                <textarea id="apiKeyTextarea" class="api-key-textarea blurred" readonly placeholder="Loading..."></textarea>
                                <button class="copy-api-btn" id="copyApiBtn"><i class="fas fa-copy"></i> Copy</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <div class="modal-overlay" id="confirmPurchaseModal">
            <div class="modal-content confirm-modal">
                <div class="modal-header"><h3>Confirm Purchase</h3><button class="close-modal">&times;</button></div>
                <div class="modal-body">
                    <div class="confirm-details">
                        <div class="detail-row"><span class="detail-label">Product:</span><span id="confirmProductName"></span></div>
                        <div class="detail-row"><span class="detail-label">Quantity:</span><span id="confirmQuantity"></span></div>
                        <div class="detail-row total-row"><span class="detail-label">Final Price:</span><span id="confirmTotalCost"></span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-secondary btn-modal-cancel">Cancel</button>
                    <button class="btn-primary btn-confirm-purchase">Confirm Purchase</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="licenseModal">
            <div class="modal-content license-modal">
                <div class="modal-header"><h3>License Created</h3><button class="close-modal">&times;</button></div>
                <div class="modal-body">
                    <div class="created-license" id="singleLicenseContainer" style="display: none;">
                        <div class="license-value" id="singleLicenseValue"></div>
                        <button class="copy-license-btn" id="copySingleBtn"><i class="fas fa-copy"></i> Copy</button>
                    </div>
                    <div class="license-list" id="multipleLicensesContainer" style="display: none;"></div>
                </div>
                <div class="modal-footer"><button class="btn-primary btn-modal-close">Close</button></div>
            </div>
        </div>

        <?php include_once('../blades/footer/footer.php'); ?>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log("Dashboard JS Initialized");
        const notyf = new Notyf({ duration: 3000, position: { x: 'right', y: 'bottom' } });

        let currentProduct = "";
        let currentProductData = {};
        let userBalance = <?= $user_balance ?>;
        let currentDiscount = 0;
        let discountedPriceToCharge = 0;
        let originalPriceBeforeDiscount = 0;
        
        let allLicenses = [];
        let filteredLicenses = [];
        let currentPage = 1;
        const licensesPerPage = 7;

        // Loyalty UI
        $('.balance-box').after(`
            <div class="loyalty-box" style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 10px; margin: 15px 0; border: 1px solid rgba(255,255,255,0.1);">
                <div class="loyalty-info">
                    <span style="display:block; font-size: 0.8em; opacity: 0.7;">Loyalty Tier</span>
                    <span id="loyaltyTier" style="font-weight: bold; color: #f1c40f;">Loading...</span>
                    <div style="height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; margin: 8px 0;">
                        <div id="loyaltyProgress" style="height: 100%; background: #f1c40f; width: 0%; border-radius: 3px; transition: 0.3s;"></div>
                    </div>
                    <small id="loyaltyNextTier" style="font-size: 0.75em; opacity: 0.6;"></small>
                </div>
            </div>`);

        function fetchLoyaltyStatus() {
            $.get('../api/reseller/loyalty-status.php', function(res) {
                if (res.success) {
                    currentDiscount = res.totalDiscount;
                    $('#loyaltyTier').html(`${res.currentTier.name} <span class="discount-badge">${res.totalDiscount}% TOTAL OFF</span>`);
                    $('#loyaltyProgress').css('width', res.progressPercentage + '%');
                    $('#loyaltyNextTier').text(res.nextTier ? `${res.purchasesNeeded} more for ${res.nextTier.name}` : 'Max Tier Reached!');
                    calculateCost();
                }
            });
        }

        function calculateCost() {
            const duration = $('#duration_type').val();
            const count = parseInt($('#count').val()) || 1;
            if (!duration || !currentProductData.name) { 
                $('#total_cost').text('$0.00'); 
                discountedPriceToCharge = 0; 
                return; 
            }

            let price = 0;
            if (duration == '1') price = currentProductData.daily_price;
            else if (duration == '7') price = currentProductData.weekly_price;
            else if (duration == '30') price = currentProductData.monthly_price;
            else if (duration == '9999') price = currentProductData.lifetime_price;

            originalPriceBeforeDiscount = price * count;
            discountedPriceToCharge = originalPriceBeforeDiscount * (1 - (currentDiscount / 100));

            if (currentDiscount > 0) {
                $('#total_cost').html(`<span class="original-price">$${originalPriceBeforeDiscount.toFixed(2)}</span><span class="discounted-price">$${discountedPriceToCharge.toFixed(2)}</span><span class="discount-badge">${currentDiscount}% OFF</span>`);
            } else {
                $('#total_cost').text(`$${originalPriceBeforeDiscount.toFixed(2)}`);
            }
        }

        function fetchLicenses(product) {
            console.log("Fetching licenses for:", product);
            $('.license-table tbody').html('<tr><td colspan="6" class="text-center">Loading...</td></tr>');
            $.get('../api/reseller/fetch-licenses.php', { productName: product }, function(res) {
                if (res.success) {
                    allLicenses = res.licenses;
                    filteredLicenses = [...allLicenses];
                    currentPage = 1; // Reset to page 1 on product switch
                    renderTable();
                } else {
                    $('.license-table tbody').html('<tr><td colspan="6" class="text-center">Failed to load licenses</td></tr>');
                }
            });
        }

        function renderTable() {
            const totalPages = Math.ceil(filteredLicenses.length / licensesPerPage) || 1;
            console.log(`Rendering Table: Page ${currentPage} of ${totalPages} | Total Items: ${filteredLicenses.length}`);
            
            const start = (currentPage - 1) * licensesPerPage;
            const end = start + licensesPerPage;
            const pageData = filteredLicenses.slice(start, end);
            
            const tbody = $('.license-table tbody').empty();
            if (pageData.length === 0) {
                tbody.append('<tr><td colspan="6" class="text-center">No licenses found</td></tr>');
            } else {
                pageData.forEach(l => {
                    tbody.append(`<tr>
                        <td>${l.key}</td>
                        <td>${l.duration}</td>
                        <td><span class="status-badge ${l.status.toLowerCase()}">${l.status}</span></td>
                        <td>${l.genby}</td>
                        <td>${l.gendate}</td>
                        <td>${l.activation_date || 'N/A'}</td>
                    </tr>`);
                });
            }

            // Update Pagination UI
            $('#prevPageBtn').prop('disabled', currentPage === 1);
            $('#nextPageBtn').prop('disabled', currentPage >= totalPages);
            $('#pageNumberDisplay').text(`Page ${currentPage} of ${totalPages}`);
        }

        // Pagination Button Listeners
        $('#prevPageBtn').on('click', function() {
            if (currentPage > 1) {
                currentPage--;
                console.log("Clicked Prev. New Page:", currentPage);
                renderTable();
            }
        });

        $('#nextPageBtn').on('click', function() {
            const totalPages = Math.ceil(filteredLicenses.length / licensesPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                console.log("Clicked Next. New Page:", currentPage);
                renderTable();
            }
        });

        // Search Logic
        $('#searchBtn').click(function() {
            const term = $('#searchInput').val().toLowerCase().trim();
            console.log("Searching for:", term);
            filteredLicenses = allLicenses.filter(l => l.key.toLowerCase().includes(term));
            currentPage = 1; // Reset to page 1 on search
            renderTable();
        });

        // Tab Switching
        $('.tab-btn').click(function() {
            $('.tab-btn').removeClass('active'); 
            $(this).addClass('active');
            currentProduct = $(this).data('product');
            currentProductData = $(this).data('product-data');
            fetchLicenses(currentProduct);
            calculateCost();
        });

        // Action Tab Switching
        $('.action-tab-btn').click(function() {
            $('.action-tab-btn').removeClass('active');
            $(this).addClass('active');
            const tab = $(this).data('tab');
            $('.action-tab-content').removeClass('active');
            $('#' + tab + '-tab').addClass('active');
        });

        $('#duration_type, #count').on('input change', calculateCost);

        // Reset HWID Handler
        $('#resetHwidBtn').click(function() {
            const key = $('#licenseKey').val().trim();
            if (!key) { notyf.error('Please enter a license key'); return; }
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Resetting...');
            $.post('../api/reseller/reset-hwid.php', { productName: currentProduct, licenseKey: key }, function(res) {
                btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i> Reset HWID');
                if (res.success) {
                    notyf.success(res.message);
                    $('#licenseKey').val('');
                    fetchLicenses(currentProduct);
                } else { notyf.error(res.message); }
            });
        });

        $('#createLicenseBtn').click(function() {
            if (!currentProduct || !$('#duration_type').val()) { notyf.error('Please select duration'); return; }
            if (discountedPriceToCharge > userBalance) { notyf.error('Insufficient balance!'); return; }
            $('#confirmProductName').text(currentProduct);
            $('#confirmQuantity').text($('#count').val());
            $('#confirmTotalCost').text('$' + discountedPriceToCharge.toFixed(2));
            $('#confirmPurchaseModal').css('display', 'flex');
        });

        $('.btn-confirm-purchase').click(function() {
            const btn = $(this);
            btn.prop('disabled', true).text('Processing...');
            $.post('../api/reseller/create-license.php', {
                productName: currentProduct,
                duration: $('#duration_type').val(),
                count: $('#count').val(),
                totalCost: originalPriceBeforeDiscount
            }, function(res) {
                btn.prop('disabled', false).text('Confirm Purchase');
                $('#confirmPurchaseModal').hide();
                if (res.success) {
                    notyf.success('Licenses generated!');
                    userBalance = res.newBalance;
                    $('#displayBalance').text('$' + userBalance.toFixed(2));
                    
                    if (res.keys.length === 1) {
                        $('#singleLicenseContainer').show();
                        $('#multipleLicensesContainer').hide();
                        $('#singleLicenseValue').text(res.keys[0]);
                    } else {
                        $('#singleLicenseContainer').hide();
                        $('#multipleLicensesContainer').show().empty();
                        res.keys.forEach(k => {
                            $('#multipleLicensesContainer').append(`<div class="license-item"><span>${k}</span><button class="copy-license-btn mini-copy" data-key="${k}"><i class="fas fa-copy"></i></button></div>`);
                        });
                        $('#multipleLicensesContainer').append(`<button class="btn-copy-all" id="copyAllBtn" style="width:100%; margin-top:10px; background:#2ecc71; border:none; padding:10px; border-radius:5px; color:white; font-weight:bold; cursor:pointer;">Copy All Licenses</button>`);
                    }
                    $('#licenseModal').css('display', 'flex');
                    fetchLicenses(currentProduct);
                    fetchLoyaltyStatus();
                } else { notyf.error(res.message); }
            });
        });

        // Copy Logic
        $('#copySingleBtn').click(function() {
            navigator.clipboard.writeText($('#singleLicenseValue').text());
            $(this).html('<i class="fas fa-check"></i> Copied!');
            setTimeout(() => $(this).html('<i class="fas fa-copy"></i> Copy'), 2000);
        });

        $(document).on('click', '.mini-copy', function() {
            navigator.clipboard.writeText($(this).data('key'));
            $(this).html('<i class="fas fa-check"></i>');
            setTimeout(() => $(this).html('<i class="fas fa-copy"></i>'), 2000);
        });

        $(document).on('click', '#copyAllBtn', function() {
            const keys = [];
            $('.license-item span').each(function() { keys.push($(this).text()); });
            navigator.clipboard.writeText(keys.join('\n'));
            $(this).text('Copied All!');
            setTimeout(() => $(this).text('Copy All Licenses'), 2000);
        });

        // API Key logic
        $.get('../api/reseller/get_token.php', res => { if (res.success) $('#apiKeyTextarea').val(res.token); });
        $('#regenerateApiBtn').click(function() {
            if(confirm('Old key will stop working. Continue?')) {
                $.post('../api/reseller/generate_token.php', res => {
                    if(res.success) { $('#apiKeyTextarea').val(res.token); notyf.success('Regenerated!'); }
                });
            }
        });
        $('#copyApiBtn').click(function() {
            navigator.clipboard.writeText($('#apiKeyTextarea').val());
            notyf.success('API Key copied!');
        });

        // Modal Closers
        $('.close-modal, .btn-modal-cancel, .btn-modal-close').click(() => $('.modal-overlay').hide());

        // Init Load
        $('.tab-btn.active').click();
        fetchLoyaltyStatus();
    });
    </script>
</body>
</html>