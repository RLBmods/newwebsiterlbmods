<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaytabsService
{
    public function createPayment(array $payload): array
    {
        $baseUrl = rtrim(config('paytabs.api_url'), '/');
        
        $response = Http::withHeaders([
            'Authorization' => config('paytabs.server_key'),
            'Content-Type' => 'application/json',
        ])->post($baseUrl . '/payment/request', $payload);

        if (! $response->successful()) {
            Log::error('PayTabs API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload
            ]);
            throw new \RuntimeException('PayTabs error: HTTP '.$response->status());
        }

        $data = $response->json();

        if (! $data || empty($data['redirect_url'])) {
            Log::error('Invalid PayTabs response', [
                'response' => $data,
                'payload' => $payload
            ]);
            throw new \RuntimeException('Invalid PayTabs response: redirect_url missing');
        }

        return $data;
    }

    public function verifyPayment(string $tranRef): array
    {
        $baseUrl = rtrim(config('paytabs.api_url'), '/');
        
        $response = Http::withHeaders([
            'Authorization' => config('paytabs.server_key'),
            'Content-Type' => 'application/json',
        ])->post($baseUrl . '/payment/query', [
            'profile_id' => (int) config('paytabs.profile_id'),
            'tran_ref' => $tranRef,
        ]);

        if (! $response->successful()) {
            Log::error('PayTabs Verify API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'tran_ref' => $tranRef
            ]);
            throw new \RuntimeException('PayTabs verify error: HTTP '.$response->status());
        }

        $data = $response->json();
        \Log::debug('PayTabs verification response', ['data' => $data]);
        
        return $data;
    }
}

