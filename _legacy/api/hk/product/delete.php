<?php
header('Content-Type: application/json');
require_once '../../../includes/session.php';
require_once '../../../includes/functions.php';
require_once '../../../db/connection.php';
require_once '../../../includes/logging.php';

requireAuth();
requireStaff();

// Initialize response
$response = ['success' => false, 'error' => ''];

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        throw new Exception('Invalid request method');
    }

    // Get JSON input
    $jsonInput = file_get_contents('php://input');
    $input = json_decode($jsonInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    // Validate product ID
    $productId = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$productId) {
        throw new Exception('Invalid product ID');
    }

    // Get product name for logging
    $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        throw new Exception('Product not found');
    }

    // Soft delete by setting visibility to 0
    $stmt = $pdo->prepare("UPDATE products SET visibility = 0 WHERE id = ?");
    $stmt->execute([$productId]);

    $response = [
        'success' => true,
        'message' => 'Product deleted successfully'
    ];

} catch (Exception $e) {
    error_log("Product deletion error: " . $e->getMessage());
    $response['error'] = $e->getMessage();
    http_response_code(400);
}

// Ensure clean output
ob_clean();
echo json_encode($response);
exit;