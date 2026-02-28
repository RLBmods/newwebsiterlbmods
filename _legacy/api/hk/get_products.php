<?php
require_once '../../includes/session.php';
require_once '../../db/connection.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Authentication
requireAuth();
requireStaff();

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id, name FROM products WHERE visibility = 1 ORDER BY name");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $products
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}