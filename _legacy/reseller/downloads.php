<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/session.php';
require_once '../includes/get_user_info.php';
include '../db/connection.php';

requireAuth(); // Ensure user is authenticated

// Get user info
$user_id = $_SESSION['user_id'] ?? null;
$current_ip = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$user_access = [];

if ($user_id) {
    $query = $con->prepare("SELECT product_access FROM usertable WHERE id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    $user = $result->fetch_assoc();
    
    if (!empty($user['product_access'])) {
        $user_access = array_map('trim', explode(',', $user['product_access']));
    }
}

// Function to log download errors
function logDownloadError($con, $key_id, $error_type, $error_details) {
    $stmt = $con->prepare("
        INSERT INTO download_errors 
        (key_id, error_type, error_details, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iss", $key_id, $error_type, $error_details);
    return $stmt->execute();
}

// Function to get remote file size
function getRemoteFileSize($url) {
    if (empty($url)) return 0;
    
    // Check if it's a local file
    if (strpos($url, 'http') !== 0) {
        $local_path = realpath(__DIR__ . $url);
        if ($local_path && file_exists($local_path)) {
            return filesize($local_path);
        }
        return 0;
    }
    
    // For remote files, try to get size via HEAD request
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $data = curl_exec($ch);
    $size = 0;
    
    if ($data !== false) {
        $content_length = 0;
        if (preg_match('/Content-Length: (\d+)/', $data, $matches)) {
            $content_length = (int)$matches[1];
        }
        $size = $content_length;
    }
    
    curl_close($ch);
    return $size;
}

// Define product access mapping
$product_access_map = [
    'Temp Spoofer' => ['Temp Spoofer'],
    'Fortnite' => ['Fortnite - Public', 'Fortnite - Private'],
    'B07' => ['B07'],
    'Rust' => ['Rust'],
    'Apex' => ['Apex Legends'],
    'Valorant' => ['Valorant Color Bot', 'Valorant Full'],
    'Perm Spoofer' => ['Perm Spoofer']
];

// Fetch all visible products
$products = $con->query("SELECT * FROM products WHERE visibility = 1 ORDER BY name ASC");
if (!$products) die("Error fetching products: " . $con->error);
$products = $products->fetch_all(MYSQLI_ASSOC);

// Status labels and colors - USING NUMERIC CODES FROM PRODUCTS TABLE
$status_labels = [
    1 => ['text' => 'UNDETECTED', 'color' => '#2ecc71', 'class' => 'undetected'],
    2 => ['text' => 'USE AT OWN RISK', 'color' => '#f39c12', 'class' => 'risk'],
    3 => ['text' => 'TESTING', 'color' => '#3498db', 'class' => 'testing'],
    4 => ['text' => 'UPDATING', 'color' => '#9b59b6', 'class' => 'updating'],
    5 => ['text' => 'OFFLINE', 'color' => '#e74c3c', 'class' => 'offline'],
    6 => ['text' => 'IN-DEVELOPMENT', 'color' => '#2980b9', 'class' => 'development'],
];

// Filter products based on user access
$accessible_products = [];
foreach ($products as $product) {
    // Check if user has access to this product
    $has_access = false;
    
    // First check direct product name matches
    if (in_array($product['name'], $user_access)) {
        $has_access = true;
    }
    
    // If no direct match, check against our access mapping
    if (!$has_access) {
        foreach ($product_access_map as $product_key => $access_levels) {
            if (stripos($product['name'], $product_key) !== false) {
                foreach ($access_levels as $access_level) {
                    if (in_array($access_level, $user_access)) {
                        $has_access = true;
                        break 2;
                    }
                }
            }
        }
    }
    
    if ($has_access) {
        // Get status info FROM PRODUCTS TABLE (numeric status field)
        $status_id = $product['status'] ?? 5; // Default to offline (5) if status not set
        $status_info = $status_labels[$status_id] ?? $status_labels[5];
        
        // Get file size from download_url
        $file_size = getRemoteFileSize($product['download_url']);
        
        // If download_url is empty, fall back to local file
        if ($file_size === 0 && !empty($product['file_name'])) {
            $file_path = realpath(__DIR__ . '/downloads/') . '/' . basename($product['file_name']);
            $file_size = file_exists($file_path) ? filesize($file_path) : 0;
        }
        
        // Add to accessible products
        $accessible_products[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'image_url' => $product['image_url'],
            'version' => $product['version'] ?? '1.0.0',
            'updated_at' => $product['updated_at'],
            'file_size' => $file_size,
            'description' => $product['description'] ?? '',
            'tutorial_link' => $product['tutorial_link'] ?? '',
            'status' => $status_info,
            'download_url' => $product['download_url'],
            'file_name' => $product['file_name']
        ];
    }
}

// Handle download request
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    
    // Key-based download validation
    if (isset($_GET['key'])) {
        $download_key = $_GET['key'];
        
        // Validate the download key
        $stmt = $con->prepare("
            SELECT dk.*, p.file_name, p.download_url, p.status as product_status
            FROM download_keys dk
            JOIN products p ON dk.product_id = p.id
            WHERE dk.key_value = ? 
            AND dk.user_id = ?
            AND dk.expiration_time > NOW()
            AND dk.status = 'unused'
            LIMIT 1
        ");
        $stmt->bind_param("si", $download_key, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Log invalid key attempt
            $error_stmt = $con->prepare("SELECT id FROM download_keys WHERE key_value = ? LIMIT 1");
            $error_stmt->bind_param("s", $download_key);
            $error_stmt->execute();
            $error_result = $error_stmt->get_result();
            $key_id = $error_result->num_rows > 0 ? $error_result->fetch_assoc()['id'] : null;
            
            if ($key_id) {
                logDownloadError($con, $key_id, 'invalid_key', json_encode([
                    'ip' => $current_ip,
                    'user_agent' => $user_agent,
                    'reason' => 'Invalid/expired/already used key'
                ]));
            }
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid, expired or already used download key']);
                exit();
            } else {
                $_SESSION['error'] = "Invalid, expired or already used download key";
                header("Location: download.php");
                exit();
            }
        }
        
        $key_data = $result->fetch_assoc();
        
        // Check if product is offline (status 5)
        if ($key_data['product_status'] == 5) {
            logDownloadError($con, $key_data['id'], 'product_offline', json_encode([
                'product_id' => $key_data['product_id'],
                'product_status' => $key_data['product_status'],
                'ip' => $current_ip,
                'user_agent' => $user_agent
            ]));
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'This product is currently offline and cannot be downloaded']);
                exit();
            } else {
                $_SESSION['error'] = "This product is currently offline and cannot be downloaded";
                header("Location: download.php");
                exit();
            }
        }
        
        // Verify IP matches the original request
        if ($key_data['ip_address'] !== $current_ip) {
            logDownloadError($con, $key_data['id'], 'ip_mismatch', json_encode([
                'original_ip' => $key_data['ip_address'],
                'current_ip' => $current_ip,
                'user_agent' => $user_agent
            ]));
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Download must be initiated from the same IP address']);
                exit();
            } else {
                $_SESSION['error'] = "Download must be initiated from the same IP address";
                header("Location: download.php");
                exit();
            }
        }

        // Mark key as used before serving file
        $update_stmt = $con->prepare("
            UPDATE download_keys 
            SET download_count = download_count + 1,
                user_agent = ?,
                used_at = NOW(),
                status = 'used',
                last_download_at = NOW()
            WHERE id = ?
        ");
        $update_stmt->bind_param("si", $user_agent, $key_data['id']);
        $update_stmt->execute();
        
        $download_url = $key_data['download_url'];
        
        // Check if it's a local file or remote URL
        if (strpos($download_url, 'http') === 0) {
            // Remote file - redirect to the URL
            header("Location: " . $download_url);
            exit();
        } else {
            // Local file - serve it directly
            $file_name = basename($download_url);
            $downloads_dir = realpath(__DIR__ . '/downloads/');
            $file_path = $downloads_dir . '/' . $file_name;
            
            if (!file_exists($file_path)) {
                logDownloadError($con, $key_data['id'], 'file_not_found', json_encode([
                    'expected_path' => $file_path,
                    'ip' => $current_ip,
                    'user_agent' => $user_agent
                ]));
                
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'File not found on server']);
                    exit();
                } else {
                    $_SESSION['error'] = "File not found on server";
                    header("Location: download.php");
                    exit();
                }
            }

            // Serve the local file
            header("Content-Description: File Transfer");
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename=\"" . basename($file_name) . "\"");
            header("Content-Length: " . filesize($file_path));
            header("Expires: 0");
            header("Cache-Control: must-revalidate");
            header("Pragma: public");
            
            readfile($file_path);
            exit();
        }
    }
    
    // Verify product exists and user has access
    $stmt = $con->prepare("
        SELECT p.* FROM products p
        WHERE p.id = ? AND p.visibility = 1
        LIMIT 1
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Check if product is offline (status 5)
        if ($product['status'] == 5) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'This product is currently offline and cannot be downloaded']);
                exit();
            } else {
                $_SESSION['error'] = "This product is currently offline and cannot be downloaded";
                header("Location: download.php");
                exit();
            }
        }
        
        // Validate user access
        $has_access = false;
        
        if (in_array($product['name'], $user_access)) {
            $has_access = true;
        }
        
        if (!$has_access) {
            foreach ($product_access_map as $product_key => $access_levels) {
                if (stripos($product['name'], $product_key) !== false) {
                    foreach ($access_levels as $access_level) {
                        if (in_array($access_level, $user_access)) {
                            $has_access = true;
                            break 2;
                        }
                    }
                }
            }
        }
        
        if (!$has_access) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => "You don't have access to this product"]);
                exit();
            } else {
                $_SESSION['error'] = "You don't have access to this product";
                header("Location: download.php");
                exit();
            }
        }

        // Generate secure download key
        $download_key = bin2hex(random_bytes(16));
        $expiration_time = date("Y-m-d H:i:s", time() + 300); // 5 minutes
        
        // Store key in database with IP and initial status as 'unused'
        $stmt = $con->prepare("
            INSERT INTO download_keys 
            (key_value, user_id, product_id, file_url, expiration_time, status, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, 'unused', ?, ?)
        ");
        $stmt->bind_param("siissss", 
            $download_key, 
            $user_id, 
            $product_id, 
            $product['download_url'], 
            $expiration_time,
            $current_ip,
            $user_agent
        );
        $stmt->execute();

        // Return JSON with the download URL including the key
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'download_url' => "download.php?id=$product_id&key=$download_key"
            ]);
            exit();
        } else {
            header("Location: download.php?id=$product_id&key=$download_key");
            exit();
        }
    } else {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Product not found']);
            exit();
        } else {
            $_SESSION['error'] = "Product not found";
            header("Location: download.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $site_name;?> • Downloads</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/download.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.11.2/css/all.css">
<link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../js/download.js" defer></script>
<script src="../js/heartbeat.js" defer></script>
<script src="../js/notify.js" defer></script>
</head>
<body>
    <!-- ========== Left Sidebar Start ========== -->
    <?php include_once('../blades/sidebar/reseller-sidebar.php'); ?>
    <!-- ========== Left Sidebar Ends ========== -->
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <div class="breadcrumbs">
                    <a href="/" class="breadcrumb-item"><i class="fas fa-home"></i></a>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-current">Download</span>
                </div>
            </div>

        <!-- ========== Left Sidebar Start ========== -->
        <?php include_once('../blades/notify/notify.php'); ?>
        <!-- ========== Left Sidebar Ends ========== -->
        
        </header>
        
        <div class="content-area-wrapper">
            <div class="downloads-hero gaming-hero">
                <div class="hero-overlay"></div>
                <div class="downloads-hero-content">
                    <div class="downloads-icon">
                        <i class="fas fa-gamepad"></i>
                    </div>
                    <h2>Your Products</h2>
                    <p class="downloads-subtitle">Access your authorized game enhancements</p>
                </div>
            </div>
            
            <div class="downloads-grid gaming-grid">
                <?php if (count($accessible_products) > 0): ?>
                    <?php foreach ($accessible_products as $product): ?>
                        <div class="download-card gaming-card">
                            <div class="card-banner">
                                <span class="status-badge <?php echo $product['status']['class']; ?>">
                                    <?php echo $product['status']['text']; ?>
                                </span>
                            </div>
                            <div class="card-image" style="background-image: url('<?php echo htmlspecialchars($product['image_url']); ?>');">
                                <div class="image-overlay"></div>
                            </div>
                            <div class="card-content">
                                <div class="card-header">
                                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <div class="version-tag">v<?php echo htmlspecialchars($product['version']); ?></div>
                                </div>
                                <div class="card-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Updated: <?php echo date('m/d/Y', strtotime($product['updated_at'])); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-file-alt"></i>
                                        <span>Size: <?php 
                                            $bytes = $product['file_size'];
                                            if ($bytes == 0) echo "0";
                                            else {
                                                $units = ['B', 'KB', 'MB', 'GB'];
                                                $i = floor(log($bytes, 1024));
                                                echo round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
                                            }
                                        ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <?php if ($product['status']['text'] === 'UNDETECTED'): ?>
                                    <button class="download-btn gaming-btn" data-product-id="<?php echo $product['id']; ?>">
                                        <i class="fas fa-download"></i> DOWNLOAD NOW
                                    </button>
                                <?php else: ?>
                                    <button class="download-btn gaming-btn disabled" disabled title="Download of this product is currently disabled.">
                                        <i class="fas fa-download"></i> DOWNLOAD DISABLED
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['tutorial_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($product['tutorial_link']); ?>" class="tutorial-btn gaming-btn" target="_blank">
                                        <i class="fas fa-video"></i> TUTORIAL
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-products-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <h3>No Products Available</h3>
                        <p>You don't currently have access to any products.</p>
                        <?php if (!empty($user_access)): ?>
                            <p>Your access level: <strong><?php echo htmlspecialchars(implode(', ', $user_access)); ?></strong></p>
                        <?php else: ?>
                            <p>No product access assigned to your account.</p>
                        <?php endif; ?>
                        <p>Please contact support if you believe this is an error.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php include_once('../blades/footer/footer.php'); ?>

    </main>

    <!-- Download Processing Modal -->
    <div class="download-modal" id="downloadModal">
        <div class="download-modal-content">
            <div class="download-spinner">
                <div class="spinner"></div>
            </div>
            <h3 class="download-status-text">PROCESSING DOWNLOAD</h3>
            <p class="download-progress-text">Preparing your files...</p>
            <div class="progress-bar">
                <div class="progress"></div>
            </div>
            <button class="btn-secondary cancel-download">Cancel Download</button>
        </div>
    </div>
</body>
</html>