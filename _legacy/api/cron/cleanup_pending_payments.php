<?php
// Cron job to cleanup pending payments that haven't been completed
// This should run every 5 hours

header('Content-Type: text/plain');
echo "Starting pending payments cleanup...\n";

// Define log file
$logDir = __DIR__ . '/../logs/';
$logFile = $logDir . 'payment_cleanup.log';

// Create log directory if it doesn't exist
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

function logCleanup($message, $data = null) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    if ($data !== null) {
        $logEntry .= " - " . json_encode($data);
    }
    $logEntry .= "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    echo $logEntry;
}

try {
    // Load database connection
require_once '../../config.php';
require_once '../../db/connection.php';

    
    logCleanup("=== PAYMENT CLEANUP STARTED ===");
    
    // Calculate the cutoff time (24 hours ago)
    $cutoffTime = date('Y-m-d H:i:s', strtotime('-24 hours'));
    
    logCleanup("Cutoff time for pending payments", ['cutoff_time' => $cutoffTime]);
    
    // Start transaction
    $con->begin_transaction();
    
    try {
        // First, get all pending transactions that are older than 24 hours
        $selectStmt = $con->prepare("
            SELECT id, order_id, user_id, amount, payment_method, gateway, created_at 
            FROM payment_transactions 
            WHERE status = 'pending' 
            AND created_at < ?
        ");
        $selectStmt->bind_param("s", $cutoffTime);
        $selectStmt->execute();
        $pendingTransactions = $selectStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $totalPending = count($pendingTransactions);
        logCleanup("Found pending transactions to cleanup", ['count' => $totalPending]);
        
        if ($totalPending > 0) {
            // Update all pending transactions to failed status
            // Note: We're only updating the 'status' column since 'payment_status' doesn't exist
            $updateStmt = $con->prepare("
                UPDATE payment_transactions 
                SET status = 'failed', 
                    updated_at = NOW()
                WHERE status = 'pending' 
                AND created_at < ?
            ");
            $updateStmt->bind_param("s", $cutoffTime);
            
            if (!$updateStmt->execute()) {
                throw new Exception("Failed to update transactions: " . $updateStmt->error);
            }
            
            $affectedRows = $updateStmt->affected_rows;
            
            logCleanup("Successfully updated transactions to failed", [
                'affected_rows' => $affectedRows
            ]);
            
            // Log each transaction that was updated
            foreach ($pendingTransactions as $transaction) {
                logCleanup("Marked transaction as failed", [
                    'transaction_id' => $transaction['id'],
                    'order_id' => $transaction['order_id'],
                    'user_id' => $transaction['user_id'],
                    'amount' => $transaction['amount'],
                    'payment_method' => $transaction['payment_method'],
                    'gateway' => $transaction['gateway'],
                    'created_at' => $transaction['created_at']
                ]);
            }
        } else {
            logCleanup("No pending transactions found older than cutoff time");
        }
        
        $con->commit();
        logCleanup("=== PAYMENT CLEANUP COMPLETED SUCCESSFULLY ===");
        
    } catch (Exception $e) {
        $con->rollback();
        throw new Exception("Database transaction failed: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    logCleanup("CLEANUP ERROR: " . $e->getMessage());
    http_response_code(500);
    exit(1);
}

echo "Pending payments cleanup completed successfully.\n";
exit(0);
?>