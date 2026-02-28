<?php
require_once '../../db/connection.php';
require_once '../../vendor/autoload.php';
require_once 'auth.php';
require_once '../../includes/logging.php';

// Authenticate reseller
$reseller = authenticateReseller($con);

// Get optional product filter
$productName = $_GET['productName'] ?? '';

// Build query to fetch licenses
$query = "SELECT * FROM reseller_licenses WHERE user_id = ?";
$params = [$reseller['user_id']];
$types = "i";

if (!empty($productName)) {
    $query .= " AND product_name = ?";
    $params[] = $productName;
    $types .= "s";
}

$query .= " ORDER BY created_at DESC";

$stmt = $con->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$licenses = [];
while ($row = $result->fetch_assoc()) {
    $licenses[] = [
        'id' => $row['id'],
        'product_name' => $row['product_name'],
        'license_key' => $row['license_key'],
        'duration' => $row['duration'] . ' ' . $row['duration_type'],
        'cost' => (float)$row['cost'],
        'created_at' => $row['created_at'],
        'expires_at' => $row['expires_at'],
        'status' => strtotime($row['expires_at']) > time() ? 'Active' : 'Expired'
    ];
}

// Log the action
logAction($reseller['user_id'], $reseller['user_name'], $reseller['user_email'], $reseller['ip_address'], 
    "API: Fetched license list" . ($productName ? " for product: $productName" : ""));

http_response_code(200);
echo json_encode([
    'success' => true,
    'licenses' => $licenses,
    'count' => count($licenses)
]);
?>