<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../../db/connection.php';
require_once '../../../vendor/autoload.php';
require_once '../../../includes/get_user_info.php';
require_once '../../../includes/session.php';
require_once '../../../includes/logging.php';

requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// 1. Inputs from the admin panel
$productName = $_POST['productName'] ?? '';
$duration = (int)($_POST['duration'] ?? 0);
// In the new DB structure, we treat durationType as a copy of duration
$durationType = $duration; 
$rawKeys = $_POST['keysText'] ?? ''; 

if (empty($productName) || empty($rawKeys) || $duration <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing product name, duration, or keys list']);
    exit;
}

// 2. Fetch product ID
$stmt = $con->prepare("SELECT id FROM products WHERE name = ?");
$stmt->bind_param("s", $productName);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found in database']);
    exit;
}

// 3. Clean and Validate Keys
$lines = explode("\n", $rawKeys);
$validKeys = [];
foreach ($lines as $line) {
    $cleanLine = trim($line);
    if (empty($cleanLine)) continue;

    // Use regex to remove "1. " or "2) " prefixes if they exist (common when copying from lists)
    $cleanKey = preg_replace('/^\d+[\.\)\s-]+/', '', $cleanLine);
    $cleanKey = trim($cleanKey);

    if (!empty($cleanKey)) {
        $validKeys[] = $cleanKey;
    }
}

if (empty($validKeys)) {
    echo json_encode(['success' => false, 'message' => 'No valid keys found in the input']);
    exit;
}

// 4. Insert into product_stock
$con->begin_transaction();
try {
    // New Table Structure: duration and duration_type are both INT
    // bind_param types: i (product_id), s (license_key), i (duration), i (duration_type)
    $insertStmt = $con->prepare("INSERT INTO product_stock (product_id, license_key, duration, duration_type, status) VALUES (?, ?, ?, ?, 'available')");
    
    $addedCount = 0;
    foreach ($validKeys as $key) {
        $insertStmt->bind_param("isii", $product['id'], $key, $duration, $durationType);
        if ($insertStmt->execute()) {
            $addedCount++;
        }
    }

    $con->commit();
    
    // Log admin action
    $admin_user = $_SESSION['user_name'] ?? 'Admin';
    logAction($_SESSION['user_id'] ?? 0, $admin_user, 'N/A', $_SERVER['REMOTE_ADDR'], "Imported $addedCount stock keys for $productName ($duration days)");

    echo json_encode([
        'success' => true, 
        'message' => "Successfully imported $addedCount keys for $productName."
    ]);

} catch (Exception $e) {
    $con->rollback();
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}