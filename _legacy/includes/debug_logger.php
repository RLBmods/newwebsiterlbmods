<?php
function debug_log($message, $data = null, $logFile = 'payment_debug.log') {
    $logDir = '/var/www/customer/rlbmods-design/logs/';
    $logPath = $logDir . $logFile;
    
    // Create log directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $logMessage .= " - " . json_encode($data, JSON_PRETTY_PRINT);
        } else {
            $logMessage .= " - " . $data;
        }
    }
    
    $logMessage .= "\n";
    
    error_log($logMessage, 3, $logPath);
}

// Function to log API requests and responses
function log_api_call($endpoint, $request, $response, $statusCode) {
    $logData = [
        'endpoint' => $endpoint,
        'request' => $request,
        'response' => $response,
        'status_code' => $statusCode,
        'timestamp' => time()
    ];
    
    debug_log("API Call", $logData, 'api_calls.log');
}
?>