<?php

return [
    'api_url' => env('NOWPAYMENTS_API_URL', 'https://api.nowpayments.io'),
    'api_key' => env('NOWPAYMENTS_API_KEY'),
    // IPN secret configured in NOWPayments dashboard (used to validate x-nowpayments-sig)
    'ipn_secret' => env('NOWPAYMENTS_IPN_SECRET'),

    // Fiat pricing currency for invoices
    'price_currency' => env('NOWPAYMENTS_PRICE_CURRENCY', 'usd'),

    // URLs for redirects + webhooks
    'ipn_callback_url' => env('NOWPAYMENTS_IPN_CALLBACK_URL', env('APP_URL') . '/webhooks/nowpayments'),
    'success_url' => env('NOWPAYMENTS_SUCCESS_URL', env('APP_URL') . '/checkout?crypto=success'),
    'cancel_url' => env('NOWPAYMENTS_CANCEL_URL', env('APP_URL') . '/checkout?crypto=cancel'),
];

