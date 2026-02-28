<?php
require_once '../../includes/session.php';
require_once '../../db/connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

$username = $_SESSION['username']; // Make sure you store username in session

try {
    // Check for new mentions
    $stmt = $con->prepare("SELECT m.id, m.message, u.name as sender 
                          FROM mentions mn
                          JOIN messages m ON mn.message_id = m.id
                          JOIN usertable u ON m.username = u.name
                          WHERE mn.mentioned_user = ? AND mn.seen = 0");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $mentions = $result->fetch_all(MYSQLI_ASSOC);
    
    // Mark as seen
    if (!empty($mentions)) {
        $stmt = $con->prepare("UPDATE mentions SET seen = 1 WHERE mentioned_user = ? AND seen = 0");
        $stmt->bind_param("s", $username);
        $stmt->execute();
    }
    
    echo json_encode(['mentions' => $mentions]);
    
} catch (Exception $e) {
    error_log("Mentions Error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal server error']);
}
?>