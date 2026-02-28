<?php
require_once '../../config.php';
require_once '../../db/connection.php';

// Get all pending transactions older than 15 minutes
$stmt = $con->prepare("SELECT order_id FROM payment_transactions 
                      WHERE status = 'pending' 
                      AND created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Use file_get_contents or curl to call your status.php
    $url = "https://s.compilecrew.xyz/api/topup/status.php?order_id=" . urlencode($row['order_id']);
    file_get_contents($url);
}