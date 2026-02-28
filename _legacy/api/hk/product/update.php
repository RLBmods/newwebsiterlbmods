<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once '../../../includes/session.php';
require_once '../../../includes/functions.php';
require_once '../../../db/connection.php';
require_once '../../../includes/logging.php';

requireAuth();
requireStaff();

// Initialize response array
$response = ['success' => false, 'error' => ''];

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. Expected POST, got ' . $_SERVER['REQUEST_METHOD']);
    }

    // Validate product ID
    $productId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$productId) {
        throw new Exception('Invalid product ID');
    }

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
    $fileName = htmlspecialchars(trim($_POST['file_name'] ?? ''));
    $downloadUrl = filter_input(INPUT_POST, 'download_url', FILTER_SANITIZE_URL);
    $version = htmlspecialchars(trim($_POST['version'] ?? '1.0.0'));
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

    // Handle image upload (existing code)
    $imageUrl = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/products/uploads/';
        
        // Check if directory exists and is writable
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Failed to create upload directory: ' . $uploadDir);
            }
        }
        
        if (!is_writable($uploadDir)) {
            throw new Exception('Upload directory is not writable: ' . $uploadDir . 
                               '. Please check permissions.');
        }
        
        // Validate image file
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
            $error = error_get_last();
            throw new Exception('Failed to move uploaded file. Error: ' . 
                               ($error['message'] ?? 'Unknown error'));
        }
    }
    
    // Handle download file upload
    $downloadFilePath = null;
    $uploadedFileName = null;
    if (isset($_FILES['download_file']) && $_FILES['download_file']['error'] === UPLOAD_ERR_OK) {
        $downloadsDir = $_SERVER['DOCUMENT_ROOT'] . '/downloads/';
        
        // Check if directory exists and is writable
        if (!is_dir($downloadsDir)) {
            if (!mkdir($downloadsDir, 0755, true)) {
                throw new Exception('Failed to create downloads directory: ' . $downloadsDir);
            }
        }
        
        if (!is_writable($downloadsDir)) {
            throw new Exception('Downloads directory is not writable: ' . $downloadsDir . 
                               '. Please check permissions.');
        }
        
        // Validate download file
        $fileTmpPath = $_FILES['download_file']['tmp_name'];
        $uploadedFileName = $_FILES['download_file']['name'];
        $fileSize = $_FILES['download_file']['size'];
        $fileType = $_FILES['download_file']['type'];
        
        // Check for upload errors
        if ($_FILES['download_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $_FILES['download_file']['error']);
        }
        
        // Check file size (max 50MB for download files)
        if ($fileSize > 50000000) {
            throw new Exception('File size too large. Maximum 50MB allowed.');
        }
        
        // Get file extension
        $fileExtension = strtolower(pathinfo($uploadedFileName, PATHINFO_EXTENSION));
        
        // Generate unique filename
        $newFileName = uniqid('download_', true) . '.' . $fileExtension;
        $destPath = $downloadsDir . $newFileName;
        
        // Move uploaded file
        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $downloadFilePath = '/downloads/' . $newFileName;
            $uploadedFileName = $uploadedFileName; // Keep original filename for display
        } else {
            $error = error_get_last();
            throw new Exception('Failed to move uploaded file. Error: ' . 
                               ($error['message'] ?? 'Unknown error'));
        }
    }
    
    // Get current data if no new data uploaded
    $stmt = $pdo->prepare("SELECT image_url, file_name, download_url FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $currentProduct = $stmt->fetch();
    
    if (!$imageUrl) {
        $imageUrl = $currentProduct['image_url'];
    }
    
    // Use uploaded file name or keep existing
    $finalFileName = $uploadedFileName ?: $fileName;
    $finalDownloadUrl = $downloadFilePath ?: $downloadUrl;

    // Update product - ADD STATUS FIELD TO THE QUERY
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
        $finalFileName, $finalDownloadUrl, $version, $status, // ADD STATUS HERE
        $productId
    ]);

    // Get the updated product
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    $response = [
        'success' => true,
        'message' => 'Product updated successfully',
        'product' => $product
    ];

} catch (Exception $e) {
    error_log("Product update error: " . $e->getMessage());
    $response['error'] = $e->getMessage();
    http_response_code(400); // Bad Request
}

// Ensure no other output is sent
header('Content-Type: application/json');
echo json_encode($response);
exit;