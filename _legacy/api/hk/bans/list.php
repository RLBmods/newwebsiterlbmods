<?php
header('Content-Type: application/json');
require_once '../../../includes/session.php';
require_once '../../../includes/functions.php';
require_once '../../../db/connection.php';
require_once '../../../includes/logging.php';
require_once '../../../includes/get_user_info.php';

// Authentication
requireAuth();
requireStaff();

$response = ['success' => false, 'bans' => []];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    // Pagination parameters
    $page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
    $perPage = 10;
    $offset = ($page - 1) * $perPage;

    // Filter parameters
    $filter = filter_input(INPUT_GET, 'filter', FILTER_SANITIZE_STRING) ?: 'all';
    $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?: '';

    // Base query
    $query = "SELECT 
    b.id, b.user_id, b.username, b.reason, b.banned_by_username, 
    b.banned_at, b.expires_at, b.is_permanent, b.is_active,
    b.unbanned_at, b.unbanned_by_username,
    CASE 
        WHEN b.is_active = 0 AND b.unbanned_at IS NOT NULL THEN 'Unbanned'
        WHEN b.is_active = 0 AND b.unbanned_at IS NULL AND b.expires_at <= NOW() THEN 'Expired'
        WHEN b.is_permanent = 1 THEN 'Permanent'
        WHEN b.expires_at > NOW() THEN 'Active'
        ELSE 'Expired'
    END as status
  FROM bans b
  WHERE 1=1";

    $params = [];
    $types = '';

    // Apply filters
// Apply filters
switch ($filter) {
    case 'active':
        $query .= " AND b.is_active = 1 AND (b.is_permanent = 1 OR b.expires_at > NOW())";
        break;
    case 'expired':
        $query .= " AND (b.is_active = 0 OR (b.is_permanent = 0 AND b.expires_at <= NOW()))";
        break;
    case 'permanent':
        $query .= " AND b.is_permanent = 1 AND b.is_active = 1";
        break;
    case 'unbanned':
        $query .= " AND b.is_active = 0 AND b.unbanned_at IS NOT NULL";
        break;
}


    // Apply search
    if ($search) {
        $query .= " AND (b.username LIKE ? OR b.reason LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    // Count total records
    $countQuery = "SELECT COUNT(*) as total FROM ($query) as subquery";
    $stmt = $pdo->prepare($countQuery);
    if ($params) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $total = $stmt->fetchColumn();

    // Get paginated results - add LIMIT and OFFSET directly to the query
    $query .= " ORDER BY b.banned_at DESC LIMIT $perPage OFFSET $offset";

    $stmt = $pdo->prepare($query);
    if ($params) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $bans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates
    foreach ($bans as &$ban) {
        $ban['banned_at'] = date('Y-m-d H:i', strtotime($ban['banned_at']));
        $ban['expires_at'] = $ban['is_permanent'] ? 'Never' : 
                            ($ban['expires_at'] ? date('Y-m-d H:i', strtotime($ban['expires_at'])) : 'N/A');
    }

    $response = [
        'success' => true,
        'bans' => $bans,
        'pagination' => [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ]
    ];

} catch (Exception $e) {
    error_log("Bans list error: " . $e->getMessage());
    $response['error'] = $e->getMessage();
    http_response_code(400);
}

ob_clean();
echo json_encode($response);
exit;