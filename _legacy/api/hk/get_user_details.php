<?php
require_once  '../../includes/session.php';
require_once  '../../db/connection.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Authentication
requireAuth();
requireStaff();

header('Content-Type: application/json');

try {
    $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
    if (!$userId) {
        throw new Exception('Invalid user ID');
    }

    $stmt = $pdo->prepare("SELECT 
        name,
        email,
        discordid, 
        balance, 
        product_access,
        role,
        banned
        FROM usertable 
        WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Convert product_access string to array
    $products = $user['product_access'] ? explode(',', $user['product_access']) : [];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'username' => $user['name'],
            'email' => $user['email'],
            'discordid' => $user['discordid'],
            'balance' => $user['balance'],
            'products' => $products,
            'role' => $user['role'],
            'banned' => (bool)$user['banned']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}