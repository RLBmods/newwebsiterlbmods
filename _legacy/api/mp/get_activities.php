<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../db/connection.php';

header('Content-Type: application/json');

try {
    // Debug logging
    error_log("get_activities.php accessed by user: " . ($_SESSION['user_id'] ?? 'unknown'));

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }

    $query = $con->prepare("SELECT * FROM activities WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $query->bind_param("i", $_SESSION['user_id']);
    $query->execute();
    $result = $query->get_result();

    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $row['time_ago'] = time_elapsed_string($row['created_at']);
        $activities[] = $row;
    }

    error_log("Found activities: " . count($activities)); // Debug logging

    echo json_encode([
        'success' => true,
        'data' => $activities
    ]);

} catch (Exception $e) {
    error_log("Error in get_activities.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}