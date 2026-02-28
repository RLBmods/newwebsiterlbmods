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


// Ensure the user is authenticated
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$userId = $_SESSION['user_id'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 7; // Number of items per page
$offset = ($page - 1) * $perPage;

try {
    // First get total count
    $countStmt = $con->prepare("
        SELECT COUNT(*) as total 
        FROM shop_purchases 
        WHERE user_id = ?
    ");
    $countStmt->bind_param("i", $userId);
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $total = $countResult['total'];
    $pages = ceil($total / $perPage);

    // Then get paginated results
    $stmt = $con->prepare("
        SELECT 
            p.name AS product_name,
            l.license_key,
            l.purchase_date,
            l.expires_at,
            l.duration,
            l.price
        FROM 
            shop_purchases l
        JOIN 
            products p ON l.product_id = p.id
        WHERE 
            l.user_id = ?
        ORDER BY 
            l.purchase_date DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->bind_param("iii", $userId, $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $purchases = [];
    while ($row = $result->fetch_assoc()) {
        // Convert duration to more readable format
        $row['duration_display'] = match($row['duration']) {
            'daily' => '1 Day',
            'weekly' => '1 Week',
            'monthly' => '1 Month',
            'lifetime' => 'Lifetime',
            default => $row['duration']
        };
        
        $purchases[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'purchases' => $purchases,
        'total' => $total,
        'pages' => $pages,
        'current_page' => $page
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching purchase history: ' . $e->getMessage()
    ]);
}