<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../../db/connection.php';
require_once '../../../vendor/autoload.php';
require_once '../../../includes/get_user_info.php';
require_once '../../../includes/session.php';
require_once '../../../includes/logging.php';

requireAuth();
requireStaff();

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
    exit;
}

$txnId = (int)$_GET['id'];

// Get transaction with user information
$stmt = $con->prepare("
    SELECT pt.*, u.name 
    FROM payment_transactions pt
    LEFT JOIN usertable u ON pt.user_id = u.id
    WHERE pt.id = ?
");
$stmt->bind_param('i', $txnId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Transaction not found']);
    exit;
}

$transaction = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'transaction' => $transaction
]);