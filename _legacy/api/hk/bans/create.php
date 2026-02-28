<?php
// Start output buffering at the very top
ob_start();

// Set headers first
header('Content-Type: application/json; charset=UTF-8');

// Then require files
require_once '../../../includes/session.php';
require_once '../../../includes/functions.php';
require_once '../../../db/connection.php';
require_once '../../../includes/logging.php';
require_once '../../../includes/get_user_info.php';

// Authentication
requireAuth();
requireStaff();

$response = ['success' => false, 'error' => ''];

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }

    // Validate required fields
    $username = trim($input['username'] ?? '');
    $reason = trim($input['reason'] ?? '');
    $duration = $input['duration'] ?? 'permanent';
    $customDate = $input['custom_date'] ?? null;

    if (empty($username) || empty($reason)) {
        throw new Exception('Username and reason are required');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM usertable WHERE name = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('User not found');
    }

    // Check for existing active ban
    $stmt = $pdo->prepare("SELECT id FROM bans WHERE user_id = ? AND is_active = 1 AND (is_permanent = 1 OR expires_at > NOW())");
    $stmt->execute([$user['id']]);
    if ($stmt->fetch()) {
        throw new Exception('User is already banned');
    }

    // Calculate expiration
    $isPermanent = ($duration === 'permanent');
    $expiresAt = null;

    if (!$isPermanent) {
        switch ($duration) {
            case '1d': $interval = 'P1D'; break;
            case '7d': $interval = 'P7D'; break;
            case '30d': $interval = 'P30D'; break;
            case 'custom': 
                if ($customDate) {
                    $expiresAt = (new DateTime($customDate))->format('Y-m-d H:i:s');
                }
                break;
            default: $interval = null;
        }

        if ($interval) {
            $expiresAt = (new DateTime())->add(new DateInterval($interval))->format('Y-m-d H:i:s');
        }
    }

    // Create ban
    $stmt = $pdo->prepare("INSERT INTO bans 
        (user_id, username, reason, banned_by, banned_by_username, expires_at, is_permanent) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $user['id'],
        $username,
        $reason,
        $_SESSION['user_id'],
        $_SESSION['user_name'],
        $expiresAt,
        $isPermanent ? 1 : 0
    ]);

    $banId = $pdo->lastInsertId();
    $pdo->commit();

    // Log action
    //logAction($pdo, $_SESSION['user_id'], 'Created ban', "Banned user $username (ID: $banId)");

    $response = [
        'success' => true,
        'message' => 'User banned successfully',
        'ban' => [
            'id' => $banId,
            'username' => $username,
            'reason' => $reason,
            'banned_by' => $_SESSION['user_name'],
            'banned_at' => date('Y-m-d H:i:s'),
            'expires_at' => $isPermanent ? 'Never' : ($expiresAt ?? 'N/A'),
            'is_permanent' => $isPermanent,
            'status' => 'Active'
        ]
    ];

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['error'] = $e->getMessage();
    http_response_code(400);
}

// Clean output and send JSON
ob_end_clean();
echo json_encode($response);
exit;