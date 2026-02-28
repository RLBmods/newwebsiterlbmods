<?php
require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json');
echo json_encode([
    'logged_in' => isLoggedIn(),
    'session_expired' => isSessionExpired(),
    'last_activity' => $_SESSION['last_activity'] ?? null,
    'user_id' => $_SESSION['user_id'] ?? null
]);
exit();