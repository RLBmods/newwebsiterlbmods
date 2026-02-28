<?php

namespace App\Services;

use App\Models\Product;
use App\Models\License;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

class LicenseProviderServiceView
{
    /**
     * Fetch licenses for a product, optionally filtered by user.
     *
     * @param Product $product
     * @param \App\Models\User|null $user
     * @return array
     */
    public function fetch(Product $product, $user = null): array
    {
        return match ($product->type) {
            'keyauth' => $this->fetchKeyAuth($product, $user),
            'pytguard' => $this->fetchPytGuard($product, $user),
            'valorant' => $this->fetchAntiVGC($product, $user),
            'stock' => $this->fetchLocal($product, $user),
            default => throw new \Exception("Unsupported product type for fetching: {$product->type}"),
        };
    }

    protected function fetchKeyAuth(Product $product, $user): array
    {
        // 1. Fetch local keys first
        $localLicenses = License::where('product_id', $product->id)
            ->when($user, fn($q) => $q->where('user_id', $user->id))
            ->get();

        if ($localLicenses->isEmpty() && $user) {
            return [];
        }

        // 2. Fetch from API for enrichment
        try {
            $response = Http::get($product->api_url, [
                'apikey' => $product->api_key,
                'type' => 'fetchallkeys',
                'format' => 'JSON',
            ]);

            $apiLicenses = $response->successful() ? collect($response->json('keys') ?? [])->keyBy('key') : collect();
        } catch (\Exception $e) {
            $apiLicenses = collect();
        }

        $results = [];
        foreach ($localLicenses as $license) {
            $keyString = $license->license_key;
            $apiData = $apiLicenses->get($keyString);

            if ($apiData) {
                $results[] = [
                    'key' => $keyString,
                    'status' => $apiData['status'] ?? 'N/A',
                    'expires_at' => isset($apiData['expires']) ? formatExpirationHelper($apiData['expires']) : 'N/A',
                    'activation_date' => isset($apiData['usedate']) ? Carbon::createFromTimestamp($apiData['usedate'])->toDateTimeString() : 'None',
                    'generated_by' => $apiData['genby'] ?? 'N/A',
                    'generated_at' => isset($apiData['gendate']) ? Carbon::createFromTimestamp($apiData['gendate'])->toDateTimeString() : $license->created_at->toDateTimeString(),
                    'raw_generated_at' => $apiData['gendate'] ?? $license->created_at->timestamp,
                    'raw_expires_at' => $apiData['expires'] ?? 0,
                ];
            } else {
                $results[] = [
                    'key' => $keyString,
                    'status' => ucfirst($license->status),
                    'expires_at' => $license->expires_at ? Carbon::parse($license->expires_at)->diffForHumans() : 'Not Activated',
                    'activation_date' => 'Pending Sync',
                    'generated_by' => $license->generated_by ?? 'System',
                    'generated_at' => $license->created_at->toDateTimeString(),
                    'raw_generated_at' => $license->created_at->timestamp,
                    'raw_expires_at' => $license->expires_at ? Carbon::parse($license->expires_at)->timestamp : 0,
                ];
            }
        }

        return $results;
    }

    protected function fetchPytGuard(Product $product, $user): array
    {
        // 1. Fetch local keys first
        $localLicenses = License::where('product_id', $product->id)
            ->when($user, fn($q) => $q->where('user_id', $user->id))
            ->get();

        if ($localLicenses->isEmpty() && $user) {
            return [];
        }

        // 2. Fetch from API for enrichment
        try {
            $response = Http::withHeaders([
                'x-access-key' => $product->api_key,
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.361'
            ])->get("{$product->api_url}get_licenses");

            $apiLicenses = $response->successful() ? collect($response->json() ?? [])->keyBy('api_key') : collect();
        } catch (\Exception $e) {
            $apiLicenses = collect();
        }

        $results = [];
        foreach ($localLicenses as $license) {
            $keyString = $license->license_key;
            $apiData = $apiLicenses->get($keyString);

            if ($apiData) {
                $results[] = [
                    'key' => $keyString,
                    'status' => isset($apiData['sid']) ? 'Active' : 'Inactive',
                    'expires_at' => $this->formatPytGuardExpiration($apiData['expiry'] ?? null),
                    'activation_date' => isset($apiData['sid']) ? 'Activated' : 'None',
                    'generated_by' => $license->generated_by ?? 'System',
                    'generated_at' => $license->created_at->toDateTimeString(),
                    'raw_generated_at' => $license->created_at->timestamp,
                    'raw_expires_at' => isset($apiData['expiry']) ? Carbon::parse($apiData['expiry'])->timestamp : 0,
                ];
            } else {
                $results[] = [
                    'key' => $keyString,
                    'status' => ucfirst($license->status),
                    'expires_at' => $license->expires_at ? Carbon::parse($license->expires_at)->diffForHumans() : 'Not Activated',
                    'activation_date' => 'Pending Sync',
                    'generated_by' => $license->generated_by ?? 'System',
                    'generated_at' => $license->created_at->toDateTimeString(),
                    'raw_generated_at' => $license->created_at->timestamp,
                    'raw_expires_at' => $license->expires_at ? Carbon::parse($license->expires_at)->timestamp : 0,
                ];
            }
        }

        return $results;
    }

    protected function fetchAntiVGC(Product $product, $user): array
    {
        // 1. Fetch local keys first (Primary source)
        $localLicenses = License::where('product_id', $product->id)
            ->when($user, fn($q) => $q->where('user_id', $user->id))
            ->get();

        if ($localLicenses->isEmpty() && $user) {
            return [];
        }

        // 2. Fetch from AntiVGC API to get status/expiration (Enrichment source)
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.antivgc.token'),
                'Accept' => 'application/json'
            ])->get("https://antivgc.com/api/licenses/list", [
                'application_id' => 6,
                'limit' => 100
            ]);

            $apiLicenses = $response->successful() ? collect($response->json('licenses') ?? [])->keyBy('license_key') : collect();
        } catch (\Exception $e) {
            $apiLicenses = collect();
        }

        $results = [];
        foreach ($localLicenses as $license) {
            $keyString = $license->license_key;
            $apiData = $apiLicenses->get($keyString);

            if ($apiData) {
                // If found in API, use API details
                $results[] = [
                    'key' => $keyString,
                    'status' => $this->calculateAntiVGCStatus($apiData),
                    'expires_at' => $this->formatAntiVGCExpiration($apiData['expires_at'] ?? null),
                    'activation_date' => $apiData['activated_at'] ? Carbon::parse($apiData['activated_at'])->toDateTimeString() : 'None',
                    'generated_by' => 'ID: ' . ($apiData['created_by'] ?? 'System'),
                    'generated_at' => $apiData['created_at'] ? Carbon::parse($apiData['created_at'])->toDateTimeString() : $license->created_at->toDateTimeString(),
                    'raw_generated_at' => $apiData['created_at'] ? Carbon::parse($apiData['created_at'])->timestamp : $license->created_at->timestamp,
                    'raw_expires_at' => $apiData['expires_at'] ? Carbon::parse($apiData['expires_at'])->timestamp : 9999999999,
                ];
            } else {
                // Not found in this API chunk or API failed, use local fallback
                $results[] = [
                    'key' => $keyString,
                    'status' => ucfirst($license->status),
                    'expires_at' => $license->expires_at ? $this->formatAntiVGCExpiration($license->expires_at) : 'Not Activated',
                    'activation_date' => 'Pending Sync',
                    'generated_by' => $license->generated_by ?? 'System',
                    'generated_at' => $license->created_at->toDateTimeString(),
                    'raw_generated_at' => $license->created_at->timestamp,
                    'raw_expires_at' => $license->expires_at ? Carbon::parse($license->expires_at)->timestamp : 0,
                ];
            }
        }

        return $results;
    }

    protected function fetchLocal(Product $product, $user): array
    {
        $query = License::where('product_id', $product->id);
        if ($user) {
            $query->where('user_id', $user->id);
        }

        $licenses = $query->latest()->get();

        return $licenses->map(fn($l) => [
            'key' => $l->license_key,
            'status' => ucfirst($l->status),
            'expires_at' => $l->expires_at ? Carbon::parse($l->expires_at)->toDateTimeString() : 'Lifetime',
            'activation_date' => 'N/A', // Stock usually handled by software
            'generated_by' => $l->generated_by ?? 'System',
            'generated_at' => $l->created_at->toDateTimeString(),
            'raw_generated_at' => $l->created_at->timestamp,
            'raw_expires_at' => $l->expires_at ? Carbon::parse($l->expires_at)->timestamp : 9999999999,
        ])->toArray();
    }

    private function formatPytGuardExpiration($expiry)
    {
        if (empty($expiry) || $expiry == 'null') return 'Not Activated';
        $expiryDate = Carbon::parse($expiry, 'UTC');
        if (now()->gt($expiryDate)) return 'Expired';
        return $expiryDate->diffForHumans();
    }

    private function calculateAntiVGCStatus($license)
    {
        if (empty($license['activated_at'])) return 'Inactive';
        if (isset($license['active']) && $license['active'] == 0) return 'Disabled';
        if (!empty($license['expires_at']) && Carbon::parse($license['expires_at'])->isPast()) return 'Expired';
        return 'Active';
    }

    private function formatAntiVGCExpiration($expiresAt)
    {
        if (!$expiresAt) return 'Lifetime';
        $date = Carbon::parse($expiresAt);
        if ($date->isPast()) return 'Expired';
        return $date->diffForHumans();
    }

    /**
     * Reset HWID for a license key.
     *
     * @param Product $product
     * @param string $licenseKey
     * @return array
     */
    public function resetHwid(Product $product, string $licenseKey): array
    {
        return match ($product->type) {
            'keyauth' => $this->resetKeyAuthHwid($product, $licenseKey),
            'pytguard' => $this->resetPytGuardHwid($product, $licenseKey),
            'valorant' => $this->resetAntiVGCHwid($product, $licenseKey),
            'stock' => ['success' => false, 'message' => 'This product does not require a manual HWID reset.'],
            default => throw new \Exception("Unsupported product type for HWID reset: {$product->type}"),
        };
    }

    protected function resetKeyAuthHwid(Product $product, string $licenseKey): array
    {
        $response = Http::post("{$product->api_url}{$product->api_key}&type=resetuser&user={$licenseKey}");

        if ($response->failed()) {
            return ['success' => false, 'message' => "KeyAuth API Error: " . $response->body()];
        }

        $body = $response->json();
        return [
            'success' => $body['success'] ?? false,
            'message' => $body['message'] ?? 'Failed to reset HWID',
        ];
    }

    protected function resetPytGuardHwid(Product $product, string $licenseKey): array
    {
        $response = Http::withHeaders([
            'x-access-key' => $product->api_key,
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.361'
        ])->post("{$product->api_url}reset-api-key/{$licenseKey}");

        if ($response->failed()) {
            return ['success' => false, 'message' => "PytGuard API Error: " . $response->body()];
        }

        $body = $response->json();
        return [
            'success' => $body['success'] ?? false,
            'message' => $body['message'] ?? 'Failed to reset HWID',
        ];
    }

    protected function resetAntiVGCHwid(Product $product, string $licenseKey): array
    {
        $token = config('services.antivgc.token');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->post("https://antivgc.com/api/licenses/reset", [
            'license_key' => $licenseKey
        ]);

        if ($response->failed()) {
            return ['success' => false, 'message' => "AntiVGC API Error: " . ($response->json('message') ?? $response->body())];
        }

        $body = $response->json();
        return [
            'success' => $body['success'] ?? ($response->status() === 200),
            'message' => $body['message'] ?? 'HWID Reset processed.',
        ];
    }
}

/**
 * Helper function for KeyAuth expiration
 */
function formatExpirationHelper($seconds)
{
    if ($seconds <= 0) return 'Expired';
    if ($seconds > 86400 * 365 * 5) return 'Lifetime';
    return now()->addSeconds($seconds)->diffForHumans();
}
