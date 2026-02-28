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

function getStatusText($statusCode) {
    $statusMap = [
        1 => 'Undetected',
        2 => 'Use at own risk',
        3 => 'Testing',
        4 => 'Updating',
        5 => 'Offline',
        6 => 'In Development'
    ];
    return $statusMap[$statusCode] ?? 'Unknown';
}

function getStatusClass($statusCode) {
    $statusClasses = [
        1 => 'status-undetected',
        2 => 'status-risk',
        3 => 'status-testing',
        4 => 'status-updating',
        5 => 'status-offline',
        6 => 'status-development'
    ];
    return $statusClasses[$statusCode] ?? 'status-unknown';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        if (isset($_POST['add_product']) || isset($_POST['update_product'])) {
            // Validate and sanitize inputs
            $name = htmlspecialchars(trim($_POST['name'] ?? ''));
            $description = htmlspecialchars(trim($_POST['description'] ?? ''));
            $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
            $dailyPrice = filter_input(INPUT_POST, 'daily_price', FILTER_VALIDATE_FLOAT);
            $weeklyPrice = filter_input(INPUT_POST, 'weekly_price', FILTER_VALIDATE_FLOAT);
            $monthlyPrice = filter_input(INPUT_POST, 'monthly_price', FILTER_VALIDATE_FLOAT);
            $lifetimePrice = filter_input(INPUT_POST, 'lifetime_price', FILTER_VALIDATE_FLOAT);
            $tutorialLink = filter_input(INPUT_POST, 'tutorial_link', FILTER_SANITIZE_URL);
            $visibility = isset($_POST['visibility']) ? 1 : 0;
            $resellerCanSell = isset($_POST['reseller_can_sell']) ? 1 : 0;
            $type = htmlspecialchars(trim($_POST['type'] ?? 'keyauth'));
            $apiUrl = filter_input(INPUT_POST, 'api_url', FILTER_SANITIZE_URL);
            $apiKey = htmlspecialchars(trim($_POST['apikey'] ?? ''));
            $licenseIdentifier = htmlspecialchars(trim($_POST['license_identifier'] ?? ''));
            $licenseLevel = filter_input(INPUT_POST, 'license_level', FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);
            $status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT, [
                'options' => [
                    'default' => 1,
                    'min_range' => 1,
                    'max_range' => 6
                ]
            ]);            
            // Validate required fields
            if (empty($name) || $price === false) {
                throw new Exception('Product name and price are required');
            }

            // Handle file upload
            $imageUrl = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/products/uploads/';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        throw new Exception('Failed to create upload directory');
                    }
                }
                
                // Validate file
                $fileTmpPath = $_FILES['image']['tmp_name'];
                $fileName = $_FILES['image']['name'];
                $fileSize = $_FILES['image']['size'];
                $fileType = $_FILES['image']['type'];
                
                // Check for upload errors
                if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('File upload error: ' . $_FILES['image']['error']);
                }
                
                // Check file size (max 5MB)
                if ($fileSize > 5000000) {
                    throw new Exception('File size too large. Maximum 5MB allowed.');
                }
                
                // Get file extension
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array($fileExtension, $allowedExtensions)) {
                    throw new Exception('Only JPG, JPEG, PNG, GIF, and WebP files are allowed');
                }
                
                // Generate unique filename
                $newFileName = uniqid('product_', true) . '.' . $fileExtension;
                $destPath = $uploadDir . $newFileName;
                
                // Move uploaded file
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $imageUrl = '/products/uploads/' . $newFileName;
                } else {
                    throw new Exception('Failed to move uploaded file');
                }
            }
            
            if (isset($_POST['update_product'])) {
                $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
                if (!$productId) {
                    throw new Exception('Invalid product ID');
                }
                
                // Get current image if no new image uploaded
                if (!$imageUrl) {
                    $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
                    $stmt->execute([$productId]);
                    $currentProduct = $stmt->fetch();
                    $imageUrl = $currentProduct['image_url'];
                }
                
                // Update product
                $stmt = $pdo->prepare("UPDATE products SET 
                name = ?, price = ?, image_url = ?, description = ?, tutorial_link = ?, visibility = ?,
                type = ?, api_url = ?, apikey = ?, `license-identifier` = ?, `license-level` = ?,
                daily_price = ?, weekly_price = ?, monthly_price = ?, lifetime_price = ?, reseller_can_sell = ?,
                file_name = ?, download_url = ?, version = ?, status = ?, updated_at = NOW()
                WHERE id = ?");
                
                $stmt->execute([
                    $name, $price, $imageUrl, $description, $tutorialLink, $visibility,
                    $type, $apiUrl, $apiKey, $licenseIdentifier, $licenseLevel,
                    $dailyPrice, $weeklyPrice, $monthlyPrice, $lifetimePrice, $resellerCanSell,
                    $finalFileName, $finalDownloadUrl, $version, $status, // Add status here
                    $productId
                ]);
                
                $action = "Updated product $productId";
                $details = "Product updated: $name";
                $message = 'Product updated successfully';
            } else {
                // Add new product - use default image if none uploaded
                $imageUrl = $imageUrl ?: '/products/default-product.png';
                
                $stmt = $pdo->prepare("INSERT INTO products (
                    name, price, image_url, description, tutorial_link, visibility, 
                    type, api_url, apikey, `license-identifier`, `license-level`,
                    daily_price, weekly_price, monthly_price, lifetime_price, reseller_can_sell,
                    file_name, download_url, version, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                
                $stmt->execute([
                    $name, $price, $imageUrl, $description, $tutorialLink, $visibility,
                    $type, $apiUrl, $apiKey, $licenseIdentifier, $licenseLevel,
                    $dailyPrice, $weeklyPrice, $monthlyPrice, $lifetimePrice, $resellerCanSell,
                    $finalFileName, $finalDownloadUrl, $version, $status // Add status here
                ]);
                
                $productId = $pdo->lastInsertId();
                $action = "Added product $productId";
                $details = "New product created: $name";
                $message = 'Product added successfully';
            }
            
            // Log the action
            logAction($pdo, $_SESSION['user_id'], $action, $details);
            
            echo json_encode(['success' => true, 'message' => $message]);
            exit();
            
        } elseif (isset($_POST['delete_product'])) {
            $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
            if (!$productId) {
                throw new Exception('Invalid product ID');
            }
            
            // Get product name for logging
            $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            // Soft delete by setting visibility to 0
            $stmt = $pdo->prepare("UPDATE products SET visibility = 0 WHERE id = ?");
            $stmt->execute([$productId]);
            
            // Log the action
            logAction($pdo, $_SESSION['user_id'], "Deleted product $productId", "Product removed: {$product['name']}");
            
            echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
            exit();
        }
    } catch (Exception $e) {
        error_log("Product management error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Get all products
$query = "SELECT * FROM products ORDER BY created_at DESC";
$products = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name;?> • Products</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/hk/dashboard.css">
    <link rel="stylesheet" href="/css/hk/products.css">
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
                    <span class="breadcrumb-current">Products</span>
                </div>
            </div>
            <?php include_once('../blades/notify/notify.php'); ?>
        </header>

        <div class="content-area-wrapper">
            <div class="hk-hero">
                <div class="banner-content">
                    <div class="hk-icon">
                        <i class="fas fa-shopping-basket"></i>
                    </div>
                    <h1>Products</h1>
                    <p class="hk-subtitle">Manage your product offerings</p>
                </div>
            </div>

            <div class="products-table-container">
                <div class="products-table-header">
                    <h2><i class="fas fa-box-open"></i> Product List</h2>
                    <button class="btn-admin btn-edit" id="add-product-btn">
                        <i class="fas fa-plus"></i> Add New Product
                    </button>
                </div>

                <div class="products-table-wrapper">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-error">
                            <?= htmlspecialchars($_SESSION['error']) ?>
                            <?php unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Price</th>
                                <th>Visibility</th>
                                <th>Status</th>
                                <th>Type</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No products found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['id']) ?></td>
                                        <td>
                                            <div class="product-name-cell">
                                                <?= htmlspecialchars($product['name']) ?>
                                            </div>
                                        </td>
                                        <td>$<?= number_format($product['price'], 2) ?></td>
                                        <td>
                                            <span class="status-badge <?= $product['visibility'] ? 'status-active' : 'status-expired' ?>">
                                                <?= $product['visibility'] ? 'Visible' : 'Hidden' ?>
                                            </span>
                                        </td>
                                        <td>
                                        <span class="status-badge <?= getStatusClass($product['status'] ?? 1) ?>">
                                            <?= getStatusText($product['status'] ?? 1) ?>
                                        </span>
                                        </td>
                                        <td><?= htmlspecialchars($product['type'] ?? 'N/A') ?></td>
                                        <td><?= date('M j, Y', strtotime($product['created_at'])) ?></td>
                                        <td>
                                            <button class="btn-admin btn-edit edit-product" data-product-id="<?= $product['id'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-admin btn-delete delete-product" data-product-id="<?= $product['id'] ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add/Edit Product Modal -->
        <div class="modal-overlay" id="product-modal">
            <div class="modal-container">
                <div class="modal-header">
                    <h3 id="modal-title">Add New Product</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="product-form" method="POST" action="products.php" enctype="multipart/form-data">
                        <input type="hidden" name="add_product" value="1">
                        <input type="hidden" id="product-id" name="product_id">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="product-name">Product Name *</label>
                                <input type="text" id="product-name" name="name" class="input-field" required>
                            </div>
                            <div class="form-group">
                                <label for="product-price">Base Price *</label>
                                <input type="number" id="product-price" name="price" class="input-field" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="product-description">Description</label>
                            <textarea id="product-description" name="description" class="textarea-field" rows="4"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="daily-price">Daily Price</label>
                                <input type="number" id="daily-price" name="daily_price" class="input-field" step="0.01" min="0">
                            </div>
                            <div class="form-group">
                                <label for="weekly-price">Weekly Price</label>
                                <input type="number" id="weekly-price" name="weekly_price" class="input-field" step="0.01" min="0">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="monthly-price">Monthly Price</label>
                                <input type="number" id="monthly-price" name="monthly_price" class="input-field" step="0.01" min="0">
                            </div>
                            <div class="form-group">
                                <label for="lifetime-price">Lifetime Price</label>
                                <input type="number" id="lifetime-price" name="lifetime_price" class="input-field" step="0.01" min="0">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="product-type">Product Type</label>
                                <select id="product-type" name="type" class="select-field">
                                    <option value="keyauth">KeyAuth</option>
                                    <option value="pytguard">PytGuard</option>
                                    <option value="privateauth">PrivateAuth</option>
                                    <option value="stock">Stock</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="license-level">License Level</label>
                                <input type="number" id="license-level" name="license_level" class="input-field" min="1" value="1">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="api-url">API URL</label>
                                <input type="text" id="api-url" name="api_url" class="input-field">
                            </div>
                            <div class="form-group">
                                <label for="apikey">API Key</label>
                                <input type="text" id="apikey" name="apikey" class="input-field">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="license-identifier">License Identifier</label>
                                <input type="text" id="license-identifier" name="license_identifier" class="input-field">
                            </div>
                            <div class="form-group">
                                <label for="tutorial-link">Tutorial Link</label>
                                <input type="url" id="tutorial-link" name="tutorial_link" class="input-field">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="product-status">Status</label>
                            <select id="product-status" name="status" class="select-field" required>
                                <option value="1">Undetected</option>
                                <option value="2">Use at own risk</option>
                                <option value="3">Testing</option>
                                <option value="4">Updating</option>
                                <option value="5">Offline</option>
                                <option value="6">In Development</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Product Image</label>
                            <div class="image-upload-container">
                                <div class="image-preview" id="image-preview">
                                    <i class="fas fa-image"></i>
                                    <span>No image selected</span>
                                </div>
                                <button type="button" class="btn-admin btn-secondary" id="upload-image-btn">
                                    <i class="fas fa-upload"></i> Upload Image
                                </button>
                                <input type="file" id="product-image" name="image" accept="image/*" style="display: none;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Product Download File</label>
                            <div class="file-upload-container">
                                <div class="file-preview" id="file-preview">
                                    <i class="fas fa-file"></i>
                                    <span>No file selected</span>
                                </div>
                                <button type="button" class="btn-admin btn-secondary" id="upload-file-btn">
                                    <i class="fas fa-upload"></i> Upload File
                                </button>
                                <input type="file" id="product-file" name="download_file" style="display: none;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="file-name">File Name (if uploaded)</label>
                            <input type="text" id="file-name" name="file_name" class="input-field" 
                                placeholder="e.g., RLBProduct.exe">
                        </div>

                        <div class="form-group">
                            <label for="download-url">Download URL (if not uploading file)</label>
                            <input type="text" id="download-url" name="download_url" class="input-field" 
                                placeholder="https://example.com/downloads/product.exe">
                        </div>

                        <div class="form-group">
                            <label for="product-version">Version</label>
                            <input type="text" id="product-version" name="version" class="input-field" 
                                value="1.0.0" placeholder="e.g., 1.0.0">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="product-visibility" name="visibility" checked>
                                    <span>Visible to customers</span>
                                </label>
                            </div>
                            <div class="form-group checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="reseller-can-sell" name="reseller_can_sell" checked>
                                    <span>Resellers can sell</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="modal-actions">
                            <button type="button" class="btn-admin btn-cancel">Cancel</button>
                            <button type="submit" class="btn-admin btn-submit">Save Product</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- ========== Footer Start ========== -->
        <?php include_once('../blades/footer/footer.php'); ?>
        <!-- ========== Footer Ends ========== -->
    </main>
    <script src="/js/hk/products.js"></script>
</body>
</html>