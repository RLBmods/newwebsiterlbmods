<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NowPaymentsService
{
    public function createInvoice(array $payload): array
    {
        $response = Http::withHeaders([
            'x-api-key' => config('nowpayments.api_key'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post(rtrim(config('nowpayments.api_url'), '/') . '/v1/invoice', $payload);

        if (! $response->successful()) {
            $message = $response->json('message') ?? 'NOWPayments error: HTTP ' . $response->status();
            throw new \RuntimeException($message);
        }

        $data = $response->json();
        $redirectUrl = $data['invoice_url'] ?? $data['redirect_url'] ?? null;

        if (! $data || ! $redirectUrl) {
            \Log::error('Invalid NOWPayments response', [
                'status' => $response->status(),
                'response' => $data,
                'payload_excerpt' => substr(json_encode($payload), 0, 100)
            ]);
            throw new \RuntimeException('Invalid NOWPayments response (missing redirect/invoice URL)');
        }

        // Standardize the URL for the rest of the application
        if (! isset($data['invoice_url'])) {
            $data['invoice_url'] = (string) $redirectUrl;
        }

        return $data;
    }

    /**
     * Validate NOWPayments IPN signature.
     * NOWPayments sends HMAC-SHA512 of the raw body with your IPN secret, in header "x-nowpayments-sig".
     */
    public function validateIpnSignature(Request $request): bool
    {
        $secret = config('nowpayments.ipn_secret');
        if (! $secret) {
            return false;
        }

        $provided = (string) $request->header('x-nowpayments-sig');
        if ($provided === '') {
            return false;
        }

        $raw = $request->getContent();
        $computed = hash_hmac('sha512', $raw, $secret);

        return hash_equals(strtolower($provided), strtolower($computed));
    }

    public function mapStatus(?string $status): string
    {
        return match ($status) {
            'finished', 'confirmed', 'sending' => 'completed',
            'partially_paid', 'waiting' => 'pending',
            'expired' => 'expired',
            'failed' => 'failed',
            'refunded' => 'refunded',
            default => 'pending',
        };
    }
}

