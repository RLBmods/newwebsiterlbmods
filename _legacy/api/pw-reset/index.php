<?php
header('Content-Type: application/json');
require_once '../../../includes/session.php';
require_once '../../../db/connection.php';

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }

    $action = $input['action'] ?? '';
    $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $code = $input['code'] ?? '';
    $newPassword = $input['newPassword'] ?? '';

    switch ($action) {
        case 'request_code':
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }

            // Check if user exists and not banned
            $stmt = $con->prepare("SELECT id, name, reset_request_time FROM usertable WHERE email = ? AND banned = 0 LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception('Email not found or account is banned');
            }

            $user = $result->fetch_assoc();
            
            // Check if a request was made in the last 60 seconds
            if ($user['reset_request_time'] && time() - strtotime($user['reset_request_time']) < 60) {
                throw new Exception('Please wait 60 seconds before requesting another code');
            }

            $reset_code = rand(100000, 999999);
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $request_time = date('Y-m-d H:i:s');

            $update_stmt = $con->prepare("UPDATE usertable SET reset_code = ?, reset_expiry = ?, reset_request_time = ? WHERE email = ?");
            $update_stmt->bind_param("isss", $reset_code, $expiry, $request_time, $email);
            
            if (!$update_stmt->execute()) {
                throw new Exception('Failed to generate reset code');
            }

            // In a real implementation, you would send the email here
            $response = [
                'success' => true,
                'message' => 'Reset code generated',
                'code' => $reset_code, // Only for testing, remove in production
                'expiry' => $expiry
            ];
            break;

        case 'verify_code':
            if (empty($email) || empty($code) || strlen($code) !== 6 || !ctype_digit($code)) {
                throw new Exception('Invalid verification code');
            }

            $current_time = date('Y-m-d H:i:s');
            $stmt = $con->prepare("SELECT id FROM usertable WHERE email = ? AND reset_code = ? AND reset_expiry > ? AND banned = 0 LIMIT 1");
            $stmt->bind_param("sis", $email, $code, $current_time);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception('Invalid reset code or code has expired');
            }

            $response = [
                'success' => true,
                'message' => 'Code verified successfully'
            ];
            break;

        case 'reset_password':
            if (empty($email) || empty($newPassword)) {
                throw new Exception('Invalid parameters');
            }

            if (strlen($newPassword) < 8) {
                throw new Exception('Password must be at least 8 characters');
            }

            if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                throw new Exception('Password must contain uppercase, lowercase, and numbers');
            }

            // Verify the user is allowed to reset (has a valid code)
            $current_time = date('Y-m-d H:i:s');
            $stmt = $con->prepare("SELECT id FROM usertable WHERE email = ? AND reset_code IS NOT NULL AND reset_expiry > ? AND banned = 0 LIMIT 1");
            $stmt->bind_param("ss", $email, $current_time);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception('Unauthorized password reset attempt');
            }

            // Hash the new password
            $hashed_password = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password and clear reset fields
            $stmt = $con->prepare("UPDATE usertable SET password = ?, reset_code = NULL, reset_expiry = NULL, reset_request_time = NULL WHERE email = ?");
            $stmt->bind_param("ss", $hashed_password, $email);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update password');
            }

            $response = [
                'success' => true,
                'message' => 'Password updated successfully'
            ];
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);