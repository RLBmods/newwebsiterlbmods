<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once  '../includes/session.php';
require_once  '../db/connection.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Authentication
requireAuth();
requireStaff();


// Set headers first to prevent any output before JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

// Check for AJAX request and authentication
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest' || 
    !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

try {
    if (!isset($_GET['user_id'])) {
        throw new Exception('User ID is required', 400);
    }

    $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
    if (!$userId) {
        throw new Exception('Invalid user ID', 400);
    }

    // Get user details
    $stmt = $pdo->prepare("SELECT * FROM usertable WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found', 404);
    }

    // Get products from products table
    $productsStmt = $pdo->query("SELECT id, name FROM products WHERE visibility = 1 ORDER BY name");
    $products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format product access
    $userProducts = $user['product_access'] ? explode(',', $user['product_access']) : [];

    // Handle null/0 discordid
    $discordDisplay = ($user['discordid'] == 0 || $user['discordid'] === null) ? 'Not Linked' : $user['discordid'];

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $user['id'],
            'username' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'banned' => (bool)$user['banned'],
            'discordid' => $user['discordid'],
            'discord_display' => $discordDisplay,
            'balance' => (float)$user['balance'],
            'products' => $userProducts,
            'available_products' => $products
        ]
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}