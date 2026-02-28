<?php
// This must be the VERY FIRST LINE to prevent any output before headers
ob_start();

// Required files - use absolute paths if possible
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../db/connection.php';

// Set error reporting
ini_set('display_errors', 0);  // Disable displaying errors to users
ini_set('log_errors', 1);      // Enable error logging
error_reporting(E_ALL);

// Authentication
try {
    requireAuth();
    requireStaff();
} catch (Exception $e) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Authentication failed']));
}

// Set JSON header
header('Content-Type: application/json');

try {
    // Validate product ID
    $productId = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
    if (!$productId || $productId < 1) {
        throw new Exception('Invalid product ID');
    }

    // Get product details
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        throw new Exception('Product not found', 404);
    }
    
    // Sanitize output (optional, depending on your needs)
    $product = array_map('htmlspecialchars', $product);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $product
    ]);
    
} catch (Exception $e) {
    // Set appropriate HTTP status code
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    
    // Log the error
    error_log("Product details error: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Clean output buffer
ob_end_flush();