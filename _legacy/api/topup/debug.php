<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';

$response = [
    'success' => false,
    'debug_info' => [
        'config_loaded' => defined('SELLSN_STORE_ID') && defined('SELLSN_BEARER_TOKEN'),
        'store_id' => defined('SELLSN_STORE_ID') ? SELLSN_STORE_ID : 'NOT_DEFINED',
        'bearer_token' => defined('SELLSN_BEARER_TOKEN') ? '***' . substr(SELLSN_BEARER_TOKEN, -4) : 'NOT_DEFINED',
        'api_tests' => []
    ],
    'error' => null
];

if (!defined('SELLSN_STORE_ID') || !defined('SELLSN_BEARER_TOKEN')) {
    $response['error'] = "Sellsn credentials not properly configured in config.php";
    echo json_encode($response);
    exit;
}

// Test multiple API endpoints
$endpointsToTest = [
    'Store Info' => 'stores/' . SELLSN_STORE_ID,
    'Products' => 'stores/' . SELLSN_STORE_ID . '/products',
    'Store Payment Methods' => 'stores/' . SELLSN_STORE_ID . '/payment-methods',
    'Store Orders' => 'stores/' . SELLSN_STORE_ID . '/orders',
    'Ping' => 'ping'
];

foreach ($endpointsToTest as $name => $endpoint) {
    $url = 'https://api.sellsn.io/' . $endpoint;
    $testResponse = [
        'endpoint' => $url,
        'status' => 'pending'
    ];
    
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . SELLSN_BEARER_TOKEN,
                'Accept: application/json',
                'User-Agent: RLBMods-Debug/1.0'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HEADER => true
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        $testResponse['http_code'] = $httpCode;
        $testResponse['headers'] = substr($rawResponse, 0, $headerSize);
        $testResponse['body'] = substr($rawResponse, $headerSize);
        
        if ($httpCode === 200) {
            $testResponse['status'] = 'success';
            // Try to decode JSON if possible
            $json = json_decode($testResponse['body'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $testResponse['data'] = $json;
            }
        } else {
            $testResponse['status'] = 'failed';
        }
        
        $testResponse['curl_error'] = curl_error($ch);
        $testResponse['curl_errno'] = curl_errno($ch);
        
    } catch (Exception $e) {
        $testResponse['status'] = 'error';
        $testResponse['error'] = $e->getMessage();
    } finally {
        if (isset($ch)) {
            curl_close($ch);
        }
    }
    
    $response['debug_info']['api_tests'][$name] = $testResponse;
}

// Check if any endpoints worked
$successfulTests = array_filter($response['debug_info']['api_tests'], function($test) {
    return $test['status'] === 'success';
});

if (count($successfulTests)) {
    $response['success'] = true;
    $response['error'] = 'Some endpoints worked';
} else {
    $response['error'] = 'All API endpoints failed';
}

echo json_encode($response);
exit;