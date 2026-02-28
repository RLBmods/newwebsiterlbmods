<?php
require_once '../../db/connection.php';
require_once '../../vendor/autoload.php';
require_once '../../includes/get_user_info.php';
require_once '../../includes/session.php';
require_once '../../includes/logging.php';

requireAuth();
requireMember();

header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // First get purchase statistics to determine dynamic thresholds
    $statsQuery = "SELECT 
                    AVG(purchase_count) as avg_purchases,
                    STD(purchase_count) as std_purchases,
                    MAX(purchase_count) as max_purchases
                   FROM (
                     SELECT product_id, COUNT(*) as purchase_count
                     FROM shop_purchases
                     GROUP BY product_id
                   ) as product_stats";
    
    $statsResult = mysqli_query($con, $statsQuery);
    $stats = mysqli_fetch_assoc($statsResult);
    
    // Calculate dynamic thresholds (top 20% of products with above-average sales)
    $minPurchases = ceil(max(
        $stats['avg_purchases'] + ($stats['std_purchases'] * 0.5), // Statistical significance
        5 // Absolute minimum
    ));
    
    // Fetch products with dynamic best seller determination
    $query = "SELECT 
                p.*,
                COALESCE(sp.purchase_count, 0) as purchase_count,
                DATEDIFF(NOW(), p.created_at) <= 30 as is_new,
                CASE
                    WHEN COALESCE(sp.purchase_count, 0) >= ? THEN 1
                    ELSE 0
                END as is_best_seller
              FROM products p
              LEFT JOIN (
                  SELECT product_id, COUNT(*) as purchase_count
                  FROM shop_purchases
                  GROUP BY product_id
              ) sp ON p.id = sp.product_id
              WHERE p.visibility = 1
              ORDER BY purchase_count DESC
              LIMIT 1000";
    
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "i", $minPurchases);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'image_url' => $row['image_url'],
            'description' => $row['description'],
            'price' => $row['price'],
            'daily_price' => $row['daily_price'],
            'weekly_price' => $row['weekly_price'],
            'monthly_price' => $row['monthly_price'],
            'lifetime_price' => $row['lifetime_price'],
            'created_at' => $row['created_at'],
            'is_new' => (bool)$row['is_new'],
            'is_best_seller' => (bool)$row['is_best_seller'],
            // Debug info:
            'purchase_count' => $row['purchase_count'],
            'threshold' => $minPurchases
        ];
    }
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'stats' => [
            'dynamic_threshold' => $minPurchases,
            'average_purchases' => $stats['avg_purchases'],
            'max_purchases' => $stats['max_purchases']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}