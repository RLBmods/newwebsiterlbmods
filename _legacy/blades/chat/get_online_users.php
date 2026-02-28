<?php
require_once '../../includes/session.php';
require_once '../../db/connection.php';

header('Content-Type: application/json');

try {
    // Get users active in last 5 minutes
    $stmt = $con->prepare("
        SELECT u.name as username, u.role, u.profile_picture as avatar
        FROM usertable u
        WHERE u.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY u.role DESC, u.name ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo json_encode([
        'users' => $result->fetch_all(MYSQLI_ASSOC)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>