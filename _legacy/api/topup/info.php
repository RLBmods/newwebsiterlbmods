<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db/connection.php';
require_once __DIR__ . '/../../includes/session.php';

$response = [
    'success' => false,
    'payment_methods' => [],
    'debug_info' => [],
    'error' => null
];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized", 401);
    }

    // Get account balance from NowPayments
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.nowpayments.io/v1/balance",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . NOWPAYMENTS_API_KEY,
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $balanceResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($httpCode === 200) {
        $balanceData = json_decode($balanceResponse, true);
        $response['debug_info']['nowpayments_balance'] = $balanceData;
    }

    // Get available currencies
    curl_setopt($ch, CURLOPT_URL, "https://api.nowpayments.io/v1/currencies");
    $currenciesResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($httpCode !== 200) {
        throw new Exception("Failed to fetch currencies from NowPayments. HTTP Code: $httpCode", 500);
    }

    $currenciesData = json_decode($currenciesResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid currencies data response", 500);
    }

    // Get minimum payment amounts
    curl_setopt($ch, CURLOPT_URL, "https://api.nowpayments.io/v1/min-amount");
    $minAmountResponse = curl_exec($ch);
    $minAmountData = json_decode($minAmountResponse, true);

    // Format payment methods
    $popularCurrencies = ['btc', 'eth', 'ltc', 'usdt', 'usdc', 'doge', 'bnb', 'sol', 'xrp', 'ada'];
    
    foreach ($currenciesData['currencies'] as $currencyCode) {
        if (in_array(strtolower($currencyCode), $popularCurrencies)) {
            $response['payment_methods'][] = [
                'code' => strtolower($currencyCode),
                'name' => strtoupper($currencyCode),
                'type' => 'crypto',
                'status' => 'active',
                'min_amount' => $minAmountData[strtolower($currencyCode)] ?? 1
            ];
        }
    }

    // Add card payment method
    $response['payment_methods'][] = [
        'code' => 'card',
        'name' => 'Credit Card',
        'type' => 'card',
        'status' => 'active',
        'min_amount' => 1
    ];

    curl_close($ch);

    $response['success'] = true;
    $response['debug_info']['gateway'] = 'NowPayments';
    $response['debug_info']['currencies_count'] = count($currenciesData['currencies']);

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
    
    // Add debug info if available
    if (isset($ch)) {
        $response['debug_info']['curl_error'] = curl_error($ch);
        $response['debug_info']['curl_errno'] = curl_errno($ch);
        curl_close($ch);
    }
}

echo json_encode($response);
exit;