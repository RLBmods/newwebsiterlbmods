<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db/connection.php';
require_once __DIR__ . '/../../includes/session.php';

$response = ['success' => false, 'methods' => [], 'error' => null];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized", 401);
    }

    // Fetch available payment methods from NowPayments
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.nowpayments.io/v1/currencies",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . NOWPAYMENTS_API_KEY,
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Failed to fetch currencies from NowPayments", 500);
    }

    $data = json_decode($apiResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid response from NowPayments API", 500);
    }

    // Map NowPayments currencies to our format
    $availableMethods = [];
    $popularCurrencies = ['btc', 'eth', 'ltc', 'usdt', 'usdc', 'doge', 'bnb', 'sol', 'xrp', 'ada'];
    
    foreach ($data['currencies'] as $currencyCode) {
        if (in_array(strtolower($currencyCode), $popularCurrencies)) {
            $availableMethods[] = [
                'code' => strtolower($currencyCode),
                'name' => strtoupper($currencyCode),
                'type' => 'crypto',
                'icon' => getMethodIcon($currencyCode)
            ];
        }
    }

    // Add card payment method if available
    $availableMethods[] = [
        'code' => 'card',
        'name' => 'Credit Card',
        'type' => 'card',
        'icon' => 'far fa-credit-card'
    ];

    $response['success'] = true;
    $response['methods'] = $availableMethods;

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
}

// Helper function to get appropriate icon for each method
function getMethodIcon($code) {
    $icons = [
        'btc' => 'fab fa-bitcoin',
        'eth' => 'fab fa-ethereum',
        'ltc' => 'fas fa-coins',
        'usdt' => 'fas fa-dollar-sign',
        'usdc' => 'fas fa-dollar-sign',
        'doge' => 'fab fa-reddit-alien',
        'bnb' => 'fab fa-bootstrap',
        'sol' => 'fas fa-bolt',
        'xrp' => 'fas fa-water',
        'ada' => 'fas fa-chart-line',
        'card' => 'far fa-credit-card'
    ];
    return $icons[strtolower($code)] ?? 'fas fa-money-bill-wave';
}

echo json_encode($response);
exit;