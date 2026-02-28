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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get JSON input
    $jsonInput = file_get_contents('php://input');
    $input = json_decode($jsonInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    // Validate and sanitize inputs
    $name = htmlspecialchars(trim($input['name'] ?? ''));
    $description = htmlspecialchars(trim($input['description'] ?? ''));
    $price = filter_var($input['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $dailyPrice = filter_var($input['daily_price'] ?? null, FILTER_VALIDATE_FLOAT);
    $weeklyPrice = filter_var($input['weekly_price'] ?? null, FILTER_VALIDATE_FLOAT);
    $monthlyPrice = filter_var($input['monthly_price'] ?? null, FILTER_VALIDATE_FLOAT);
    $lifetimePrice = filter_var($input['lifetime_price'] ?? null, FILTER_VALIDATE_FLOAT);
    $tutorialLink = filter_var($input['tutorial_link'] ?? '', FILTER_SANITIZE_URL);
    $visibility = isset($input['visibility']) ? 1 : 0;
    $resellerCanSell = isset($input['reseller_can_sell']) ? 1 : 0;
    $type = htmlspecialchars(trim($input['type'] ?? 'keyauth'));
    $apiUrl = filter_var($input['api_url'] ?? '', FILTER_SANITIZE_URL);
    $apiKey = htmlspecialchars(trim($input['apikey'] ?? ''));
    $licenseIdentifier = htmlspecialchars(trim($input['license_identifier'] ?? ''));
    $licenseLevel = filter_var($input['license_level'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);
    $imageUrl = filter_var($input['image_url'] ?? '/products/default-product.png', FILTER_SANITIZE_URL);

    // Validate required fields
    if (empty($name) || $price === false) {
        throw new Exception('Product name and price are required');
    }

    // Add new product
    $stmt = $pdo->prepare("INSERT INTO products (
        name, price, image_url, description, tutorial_link, visibility, 
        type, api_url, apikey, `license-identifier`, `license-level`,
        daily_price, weekly_price, monthly_price, lifetime_price, reseller_can_sell,
        created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

    $stmt->execute([
        $name, $price, $imageUrl, $description, $tutorialLink, $visibility,
        $type, $apiUrl, $apiKey, $licenseIdentifier, $licenseLevel,
        $dailyPrice, $weeklyPrice, $monthlyPrice, $lifetimePrice, $resellerCanSell
    ]);

    $productId = $pdo->lastInsertId();
    
    // Get the newly created product
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    $response = [
        'success' => true,
        'message' => 'Product created successfully',
        'product' => $product
    ];

} catch (Exception $e) {
    error_log("Product creation error: " . $e->getMessage());
    $response['error'] = $e->getMessage();
    http_response_code(400);
}

// Ensure clean output
ob_clean();
echo json_encode($response);
exit;