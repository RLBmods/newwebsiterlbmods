<?php
require_once '../../../includes/session.php';
require_once '../../../db/connection.php';
requireAuth();

header('Content-Type: application/json');

// Get query parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? min(max(5, intval($_GET['per_page'])), 100) : 10;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Calculate offset for pagination
$offset = ($page - 1) * $perPage;

// Build base query
$query = "SELECT dh.*, u.username, p.name as product_name 
          FROM download_history dh
          JOIN users u ON dh.user_id = u.id
          JOIN products p ON dh.product_id = p.id";

// Add conditions based on filter
$conditions = [];
$params = [];
$types = '';

switch ($filter) {
    case 'recent':
        $conditions[] = "dh.download_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'flagged':
        $conditions[] = "dh.status = 'flagged'";
        break;
    case 'banned':
        $conditions[] = "dh.status = 'banned'";
        break;
    case 'valid':
        $conditions[] = "dh.status = 'valid'";
        break;
    // 'all' shows everything
}

// Add search condition if provided
if (!empty($search)) {
    $conditions[] = "(u.username LIKE ? OR dh.file_name LIKE ? OR dh.download_id LIKE ? OR dh.ip_address LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= 'ssss';
}

// Combine conditions
if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM ($query) as total_query";
$countStmt = $con->prepare($countQuery);

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$totalResult = $countStmt->get_result();
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $perPage);

// Add sorting and pagination to main query
$query .= " ORDER BY dh.download_date DESC LIMIT ? OFFSET ?";
$params = array_merge($params, [$perPage, $offset]);
$types .= 'ii';

// Execute main query
$stmt = $con->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$downloads = $result->fetch_all(MYSQLI_ASSOC);

// Format dates and prepare response
$formattedDownloads = array_map(function($download) {
    return [
        'id' => $download['id'],
        'user_id' => $download['user_id'],
        'username' => $download['username'],
        'product_id' => $download['product_id'],
        'product_name' => $download['product_name'],
        'file_name' => $download['file_name'],
        'version' => $download['version'],
        'download_id' => $download['download_id'],
        'ip_address' => $download['ip_address'],
        'user_agent' => $download['user_agent'],
        'download_date' => date('Y-m-d H:i', strtotime($download['download_date'])),
        'status' => $download['status'],
        'checksum' => $download['checksum'],
        'license_key' => $download['license_key']
    ];
}, $downloads);

// Return JSON response
echo json_encode([
    'success' => true,
    'data' => $formattedDownloads,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $perPage,
        'total' => $totalRows,
        'total_pages' => $totalPages
    ]
]);