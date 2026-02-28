<?php

return [
    'profile_id' => env('PAYTABS_PROFILE_ID'),
    'server_key' => env('PAYTABS_SERVER_KEY'),
    'api_url' => env('PAYTABS_API_URL', 'https://secure.paytabs.com'),
    'currency' => env('PAYTABS_CURRENCY', 'USD'),
    'callback_url' => env('PAYTABS_CALLBACK_URL'), // server-to-server webhook (optional for now)
    'return_url' => env('PAYTABS_RETURN_URL', env('APP_URL').'/checkout/paytabs/complete'),
];

