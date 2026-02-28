<?php
// Payment Status Cron Job
// Example crontab: */5 * * * * /usr/bin/php /var/www/customer/rlbmods-design/api/topup/cron/check_payments.php

// ---------------------------
// Logging configuration
// ---------------------------
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Build log dir relative to this script
$logDir = __DIR__ . '/../../logs/';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// set PHP error log file to the same logs folder
ini_set('log_errors', 1);
ini_set('error_log', $logDir . 'cron_errors.log');

$logFile = $logDir . 'payment_cron.log';

function logCronMessage($message, $data = null) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    if ($data !== null) {
        // make sure JSON encode doesn't fail
        $json = @json_encode($data);
        $logEntry .= " - " . ($json === false ? print_r($data, true) : $json);
    }
    $logEntry .= PHP_EOL;
    // Use file_put_contents with LOCK_EX to avoid concurrent writes
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Start execution
logCronMessage("=== PAYMENT STATUS CRON JOB STARTED ===");

try {
    // ---------------------------
    // Include configuration + DB
    // ---------------------------
    require_once __DIR__ . '/../../../config.php';
    require_once __DIR__ . '/../../../db/connection.php';

    if (!isset($con) || !$con) {
        throw new Exception("Database connection not available ( \$con missing )");
    }

    logCronMessage("Database connection established");

    // ---------------------------
    // Fetch pending 'nowpayments' transactions (no 24h limit)
    // ---------------------------
    $stmt = $con->prepare("
        SELECT 
            t.*,
            u.email,
            u.balance
        FROM payment_transactions t
        JOIN usertable u ON t.user_id = u.id
        WHERE t.status = 'pending'
        AND t.gateway = 'nowpayments'
        ORDER BY t.created_at DESC
    ");

    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $con->error);
    }

    if (!$stmt->execute()) {
        throw new Exception("Failed to execute select: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $pendingTransactions = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    $totalTransactions = count($pendingTransactions);
    logCronMessage("Found {$totalTransactions} pending transactions");

    $updatedCount = 0;
    $completedCount = 0;
    $failedCount = 0;

    foreach ($pendingTransactions as $transaction) {
        try {
            logCronMessage("Checking transaction", [
                'id' => $transaction['id'],
                'order_id' => $transaction['order_id'] ?? null,
                'payment_id' => $transaction['transaction_id'] ?? null
            ]);

            $paymentUpdated = checkNowPaymentsTransaction($transaction, $con);

            if ($paymentUpdated) {
                $updatedCount++;

                if ($paymentUpdated['status'] === 'completed') {
                    $completedCount++;
                } elseif (in_array($paymentUpdated['status'], ['failed', 'expired'])) {
                    $failedCount++;
                }
            }

            // small delay to avoid possible rate limits
            usleep(250000); // 0.25s

        } catch (Exception $e) {
            logCronMessage("Error processing transaction {$transaction['id']}: " . $e->getMessage());
            continue;
        }
    }

    // Log summary
    logCronMessage("Cron job completed", [
        'total_checked' => $totalTransactions,
        'updated' => $updatedCount,
        'completed' => $completedCount,
        'failed' => $failedCount
    ]);

    // Clean up old failed transactions (older than 7 days)
    cleanOldFailedTransactions($con);

} catch (Exception $e) {
    logCronMessage("CRITICAL ERROR: " . $e->getMessage());
    exit(1);
}

logCronMessage("=== PAYMENT STATUS CRON JOB COMPLETED ===");


/**
 * Check and update NowPayments transaction status
 *
 * @param array $transaction Row from payment_transactions
 * @param mysqli $con       Database connection
 * @return array|false      Returns array with status info if updated, false otherwise
 */
function checkNowPaymentsTransaction(array $transaction, mysqli $con) {
    // Require API key defined in config.php as NOWPAYMENTS_API_KEY
    if (!defined('NOWPAYMENTS_API_KEY') || empty(NOWPAYMENTS_API_KEY)) {
        logCronMessage("NOWPAYMENTS_API_KEY not defined");
        return false;
    }

    $paymentId = $transaction['transaction_id'] ?? null;

    if (empty($paymentId)) {
        logCronMessage("Skipping transaction without payment ID", ['id' => $transaction['id'] ?? null]);
        return false;
    }

    // Make API request to NowPayments
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.nowpayments.io/v1/payment/" . urlencode($paymentId),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . NOWPAYMENTS_API_KEY,
            'Accept: application/json',
            'User-Agent: RLBmods-Cron/1.0'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        logCronMessage("cURL error when fetching payment {$paymentId}", ['error' => $curlError]);
        return false;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        logCronMessage("API request returned HTTP code {$httpCode} for payment {$paymentId}", [
            'response' => substr($response, 0, 1000)
        ]);
        return false;
    }

    $apiData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logCronMessage("Invalid JSON response for payment {$paymentId}", ['json_error' => json_last_error_msg()]);
        return false;
    }

    // Map NowPayments status to our system
    $apiStatus = $apiData['payment_status'] ?? ($apiData['status'] ?? 'waiting');
    $currentStatus = $transaction['status'] ?? 'pending';

    $statusMapping = [
        'finished' => 'completed',
        'confirmed' => 'completed',
        'sending' => 'completed',
        'partially_paid' => 'pending',
        'waiting' => 'pending',
        'expired' => 'failed',
        'failed' => 'failed',
        'refunded' => 'refunded'
    ];

    $newStatus = $statusMapping[$apiStatus] ?? $currentStatus;

    // If status changed, update the transaction
    if ($newStatus !== $currentStatus) {
        logCronMessage("Status change detected", [
            'transaction_id' => $transaction['id'],
            'old_status' => $currentStatus,
            'new_status' => $newStatus,
            'api_status' => $apiStatus
        ]);

        // Begin DB transaction
        $con->begin_transaction();

        try {
            // Prepare update (note: do not store raw $apiStatus into 'status' field)
            $updateStmt = $con->prepare("
                UPDATE payment_transactions
                SET status = ?,
                    updated_at = NOW(),
                    amount_received = ?,
                    network_fee = ?,
                    confirmations = ?
                WHERE id = ?
            ");
            if (!$updateStmt) {
                throw new Exception("Failed to prepare updateStmt: " . $con->error);
            }

            // Derive numeric values (use existing transaction values as fallback)
            $amountReceived = null;
            if (isset($apiData['actually_paid'])) {
                $amountReceived = $apiData['actually_paid'];
            } elseif (isset($apiData['paid_amount'])) {
                $amountReceived = $apiData['paid_amount'];
            } elseif (isset($transaction['amount_received'])) {
                $amountReceived = $transaction['amount_received'];
            } else {
                $amountReceived = $transaction['amount'] ?? 0.0;
            }

            $networkFee = $apiData['outgoing_network_fee'] ?? ($transaction['network_fee'] ?? 0.0);
            $confirmations = isset($apiData['confirmations']) ? (int)$apiData['confirmations'] : (int)($transaction['confirmations'] ?? 0);

            // Bind parameters: status (s), amount_received (d), network_fee (d), confirmations (i), id (i)
            if (!$updateStmt->bind_param("sddii", $newStatus, $amountReceived, $networkFee, $confirmations, $transaction['id'])) {
                throw new Exception("Failed to bind params for updateStmt: " . $updateStmt->error);
            }

            if (!$updateStmt->execute()) {
                throw new Exception("Failed to execute updateStmt: " . $updateStmt->error);
            }
            $updateStmt->close();

            // If payment is now completed and wasn't completed before -> credit user
            if ($newStatus === 'completed' && $currentStatus !== 'completed') {
                $userId = (int)$transaction['user_id'];
                // The amount to add: prefer amountReceived, fallback to transaction['amount']
                $amountToAdd = (float)$amountReceived;
                if ($amountToAdd <= 0) {
                    $amountToAdd = (float)($transaction['amount'] ?? 0.0);
                }

                logCronMessage("Adding balance to user", [
                    'user_id' => $userId,
                    'amount' => $amountToAdd,
                    'previous_balance' => $transaction['balance'] ?? null
                ]);

                // Update balance
                $balanceStmt = $con->prepare("
                    UPDATE usertable
                    SET balance = balance + ?
                    WHERE id = ?
                ");
                if (!$balanceStmt) {
                    throw new Exception("Failed to prepare balanceStmt: " . $con->error);
                }
                if (!$balanceStmt->bind_param("di", $amountToAdd, $userId)) {
                    throw new Exception("Failed to bind balanceStmt params: " . $balanceStmt->error);
                }
                if (!$balanceStmt->execute()) {
                    throw new Exception("Failed to execute balanceStmt: " . $balanceStmt->error);
                }
                $balanceStmt->close();

                // Optionally record a ledger / transaction in a separate table if you have one
                // (not implemented here, but recommended for audit trail)
            }

            $con->commit();

            logCronMessage("Transaction updated successfully", [
                'transaction_id' => $transaction['id'],
                'status' => $newStatus
            ]);

            return [
                'status' => $newStatus,
                'amount_received' => $amountReceived,
                'confirmations' => $confirmations
            ];

        } catch (Exception $e) {
            $con->rollback();
            logCronMessage("Database transaction failed while updating transaction {$transaction['id']}: " . $e->getMessage());
            return false;
        }
    } else {
        logCronMessage("No status change", [
            'transaction_id' => $transaction['id'],
            'status' => $currentStatus
        ]);
    }

    return false;
}

/**
 * Clean up old failed transactions older than 7 days.
 *
 * @param mysqli $con
 * @return void
 */
function cleanOldFailedTransactions(mysqli $con) {
    try {
        // Delete or archive strategy: here we delete rows that are failed and older than 7 days.
        // Change to "UPDATE ... SET archived = 1" if you prefer to keep the rows.
        $stmt = $con->prepare("
            DELETE FROM payment_transactions
            WHERE status = 'failed'
            AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        if (!$stmt) {
            logCronMessage("Failed to prepare cleanOldFailedTransactions statement: " . $con->error);
            return;
        }

        if (!$stmt->execute()) {
            logCronMessage("Failed to execute cleanOldFailedTransactions: " . $stmt->error);
            $stmt->close();
            return;
        }

        $deleted = $stmt->affected_rows;
        $stmt->close();

        logCronMessage("Cleaned old failed transactions", ['deleted' => $deleted]);

    } catch (Exception $e) {
        logCronMessage("Error in cleanOldFailedTransactions: " . $e->getMessage());
    }
}
?>
