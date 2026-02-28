<?php
// /api/topup/cron/test_cron.php\

require_once '../../../config.php';
require_once '../../../db/connection.php';

echo "Testing cron job system...\n";

// Count pending transactions
$stmt = $con->prepare("SELECT COUNT(*) as count FROM payment_transactions WHERE status = 'pending'");
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo "Pending transactions: " . $result['count'] . "\n";

// Test API connection
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.nowpayments.io/v1/status",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['x-api-key: ' . NOWPAYMENTS_API_KEY],
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode === 200) {
    echo "NowPayments API: OK\n";
} else {
    echo "NowPayments API: FAILED (HTTP $httpCode)\n";
}

echo "Test completed.\n";
?>