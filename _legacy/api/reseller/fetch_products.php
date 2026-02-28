<?php
require_once '../../db/connection.php';
require_once '../../includes/session.php';
require_once '../../includes/logging.php';

header('Content-Type: application/json');

try {
    // Check if user is authenticated (if needed)
    $isAuthenticated = isset($_SESSION['user_id']);
    
    // Base query for visible products
    $query = "SELECT name, reseller_can_sell FROM products WHERE visibility = 1";
    
    // If user is authenticated and is a reseller, only show products they can sell
    if ($isAuthenticated && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'reseller') {
        $query .= " AND reseller_can_sell = 1";
    }
    
    // Prepare and execute the query
    $stmt = $con->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        // For API response, we can include additional info if needed
        $productData = [
            'name' => $row['name'],
            'reseller_can_sell' => (bool)$row['reseller_can_sell']
        ];
        
        $products[] = $productData;
    }

    // Log the fetch action if needed
    if ($isAuthenticated) {
        $user_id = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['user_name'] ?? 'Unknown';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        logAction($user_id, $username, $ip_address, "Fetched product list");
    }

    echo json_encode([
        'success' => true,
        'data' => $products,
        'count' => count($products)
    ]);
    
} catch (Exception $e) {
    error_log("Product fetch error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching products: ' . $e->getMessage()
    ]);
}