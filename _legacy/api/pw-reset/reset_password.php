<?php
header('Content-Type: application/json');
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../db/connection.php';
require_once '../../includes/logging.php';

require '../../vendor/autoload.php';

$response = ['success' => false, 'message' => '', 'errors' => []];

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    http_response_code(405);
    echo json_encode($response);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Invalid JSON input';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

// Validate required fields
$required_fields = ['email', 'reset_code', 'new_password', 'confirm_password'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        $response['errors'][$field] = 'This field is required';
    }
}

if (!empty($response['errors'])) {
    $response['message'] = 'Validation failed';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

// Extract data
$email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
$reset_code = trim($input['reset_code']);
$new_password = $input['new_password'];
$confirm_password = $input['confirm_password'];

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['errors']['email'] = 'Invalid email format';
}

// Validate password
if ($new_password !== $confirm_password) {
    $response['errors']['confirm_password'] = 'Passwords do not match';
} elseif (strlen($new_password) < 8) {
    $response['errors']['new_password'] = 'Password must be at least 8 characters';
} elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
    $response['errors']['new_password'] = 'Password must contain uppercase, lowercase, and numbers';
}

if (!empty($response['errors'])) {
    $response['message'] = 'Validation failed';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

// Check reset code
$current_time = date('Y-m-d H:i:s');
$stmt = $con->prepare("SELECT id FROM usertable WHERE email = ? AND reset_code = ? AND reset_expiry > ? AND banned = 0 LIMIT 1");
$stmt->bind_param("sis", $email, $reset_code, $current_time);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $response['message'] = 'Invalid reset code or code has expired';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

// Update password
$hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
$update_stmt = $con->prepare("UPDATE usertable SET password = ?, reset_code = NULL, reset_expiry = NULL WHERE email = ?");
$update_stmt->bind_param("ss", $hashed_password, $email);

if ($update_stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Password has been successfully reset';
    
    // Log password reset
    logAction(null, null, $email, $_SERVER['REMOTE_ADDR'], "Password Reset Success via API");
} else {
    $response['message'] = 'Failed to reset password. Please try again.';
    http_response_code(500);
}

echo json_encode($response);