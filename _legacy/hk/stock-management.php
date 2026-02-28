../api/hk/licenses/stockkeys.php<?php
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

// Fetch products that are set to 'stock' type
$stockProducts = [];
$query = "SELECT id, name FROM products WHERE type = 'stock'";
$result = $con->query($query);
while ($row = $result->fetch_assoc()) {
    $stockProducts[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management • <?php echo htmlspecialchars($site_name); ?></title>
    
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/hk/dashboard.css">
    <link rel="stylesheet" href="/css/hk/products.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.11.2/css/all.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                    <span class="breadcrumb-current">Stock Management</span>
                </div>
            </div>
            <?php include_once('../blades/notify/notify.php'); ?>
        </header>

        <div class="content-area-wrapper">
            <div class="hk-hero">
                <div class="banner-content">
                    <div class="hk-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <h1>Stock Management</h1>
                    <p class="hk-subtitle">Upload bulk license keys for manual stock products</p>
                </div>
            </div>

            <div class="products-table-container" style="padding: 20px;">
                <div class="products-table-header">
                    <h2><i class="fas fa-plus-circle"></i> Bulk Import Keys</h2>
                </div>

                <div class="card-body" style="background: var(--card-bg); border-radius: 8px; padding: 25px; margin-top: 20px;">
                    <form id="stockForm">
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label for="productName" style="color: var(--text-main); font-weight: 600;">Target Product</label>
                                <select name="productName" id="productName" class="select-field" style="width: 100%;" required>
                                    <option value="">-- Select a Stock-based Product --</option>
                                    <?php foreach ($stockProducts as $p): ?>
                                        <option value="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-clock mr-2"></i>Duration</label>
                            <select name="duration" class="form-control" required>
                                <option value="" disabled selected>Select Duration</option>
                                <option value="1">Daily (1 Day)</option>
                                <option value="7">Weekly (7 Days)</option>
                                <option value="30">Monthly (30 Days)</option>
                                <option value="9999">Lifetime</option>
                            </select>
                            <input type="hidden" name="durationType" id="durationTypeHidden">
                        </div>
                        <div class="form-group" style="margin-top: 20px;">
                            <label style="color: var(--text-main); font-weight: 600;">License Keys (One per line)</label>
                            <textarea name="keysText" class="textarea-field" rows="10" placeholder="1. FN-KEY-1234&#10;2. FN-KEY-5678" style="width: 100%; height: 250px; font-family: monospace;" required></textarea>
                            <small style="color: var(--text-muted); display: block; margin-top: 5px;">The system will automatically remove line numbers (e.g., "1. " or "2) ").</small>
                        </div>

                        <div class="modal-actions" style="justify-content: flex-start; margin-top: 30px;">
                            <button type="submit" class="btn-admin btn-submit" id="submitBtn">
                                <i class="fas fa-file-import"></i> <span class="btn-text">Import Stock</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include_once('../blades/footer/footer.php'); ?>
        </main>

    <script>
        
        
document.querySelector('select[name="duration"]').addEventListener('change', function() {
    document.getElementById('durationTypeHidden').value = this.value;
});
    $(document).ready(function() {
        $('#stockForm').on('submit', function(e) {
            e.preventDefault();
            
            const btn = $('#submitBtn');
            const btnText = btn.find('.btn-text');
            
            btn.prop('disabled', true);
            btnText.text('Processing...');

            $.ajax({
                url: '../api/hk/licenses/stockkeys.php', // Ensure this points to the backend script
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Stock Updated',
                            text: response.message,
                            background: 'var(--card-bg)',
                            color: 'var(--text-main)',
                            confirmButtonColor: '#5d67ff'
                        });
                        $('#stockForm')[0].reset();
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server communication error.', 'error');
                },
                complete: function() {
                    btn.prop('disabled', false);
                    btnText.text('Import Stock');
                }
            });
        });
    });
    </script>
</body>
</html>