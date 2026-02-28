<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../db/connection.php';

header('Content-Type: application/json');

try {
    // Update session activity
    $_SESSION['last_activity'] = time();
    
    // Update database activity if user is logged in
    if (isset($_SESSION['user_id'])) {
        $stmt = $con->prepare("UPDATE usertable SET last_activity = NOW() WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Session ping error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit();