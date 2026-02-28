<?php
function isStaff() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'moderator', 'developer']);
}

function canDeleteMessage($messageUserId) {
    // Staff can delete any message
    if (isStaff()) return true;
    
    // Users can delete their own messages
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] == $messageUserId;
}

function logModerationAction($action, $targetId, $details = []) {
    global $con;
    
    $stmt = $con->prepare("INSERT INTO moderation_log 
                          (moderator_id, action, target_id, target_type, details) 
                          VALUES (?, ?, ?, 'message', ?)");
    $detailsJson = json_encode($details);
    $stmt->bind_param("isss", $_SESSION['user_id'], $action, $targetId, $detailsJson);
    $stmt->execute();
}