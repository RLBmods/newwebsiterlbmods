<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LicenseProviderService
{
    /**
     * Generate licenses for a product.
     *
     * @param Product $product
     * @param int $duration
     * @param string $durationType
     * @param int $count
     * @param string $generatedBy
     * @return array
     */
    public function generate(Product $product, int $duration, string $durationType, int $count = 1, string $generatedBy = 'System'): array
    {
        return match ($product->type) {
            'keyauth' => $this->generateKeyAuth($product, $duration, $count, $generatedBy),
            'pytguard' => $this->generatePytGuard($product, $duration, $count),
            'valorant' => $this->generateAntiVGC($product, $duration, $count),
            'stock' => $this->pullFromStock($product, $duration, $durationType, $count),
            default => throw new \Exception("Unsupported product type: {$product->type}"),
        };
    }

    protected function generateKeyAuth(Product $product, int $duration, int $count, string $generatedBy): array
    {
        $response = Http::get($product->api_url, [
            'apikey' => $product->api_key,
            'type' => 'add',
            'format' => 'JSON',
            'owner' => $generatedBy,
            'mask' => $product->license_identifier . '-****-****-****',
            'expiry' => $duration,
            'amount' => $count,
            'level' => $product->license_level,
        ]);

        if ($response->failed()) {
            throw new \Exception("KeyAuth API Error: " . $response->body());
        }

        $body = $response->json();
        $keys = $body['keys'] ?? (isset($body['key']) ? [$body['key']] : []);

        if (empty($keys)) {
            throw new \Exception("KeyAuth returned no keys: " . $response->body());
        }

        return array_map(fn($key) => [
            'key' => $key,
            'expires_at' => now()->addDays($duration),
        ], $keys);
    }

    protected function generatePytGuard(Product $product, int $duration, int $count): array
    {
        $keys = [];
        for ($i = 0; $i < $count; $i++) {
            $mask = $product->license_identifier . '-' . Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4);
            $response = Http::withHeaders([
                'x-access-key' => $product->api_key,
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.361'
            ])
                ->get("{$product->api_url}create_license/{$mask}", [
                    'expiry_days' => $duration
                ]);

            if ($response->failed() || !str_contains($response->body(), 'successfully')) {
                throw new \Exception("PytGuard API Error: " . $response->body());
            }

            $keys[] = [
                'key' => $mask,
                'expires_at' => now()->addDays($duration),
            ];
        }

        return $keys;
    }

    protected function generateAntiVGC(Product $product, int $duration, int $count): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.antivgc.token'),
            'Accept' => 'application/json'
        ])->post("https://antivgc.com/api/licenses/generate", [
            'duration' => $duration,
            'quantity' => $count,
            'product' => $product->license_identifier ?? 'RLBMODS',
            'application_id' => 6 // Hardcoded as per legacy
        ]);

        if ($response->failed() || !$response->json('success')) {
            throw new \Exception("AntiVGC API Error: " . ($response->json('message') ?? $response->body()));
        }

        $keys = $response->json('licenses') ?? $response->json('keys') ?? [];
        return array_map(fn($item) => [
            'key' => is_array($item) ? ($item['license_key'] ?? $item['key']) : $item,
            'expires_at' => now()->addDays($duration),
        ], $keys);
    }

    protected function pullFromStock(Product $product, int $duration, string $durationType, int $count): array
    {
        return DB::transaction(function () use ($product, $duration, $durationType, $count) {
            $items = ProductStock::where('product_id', $product->id)
                ->where('duration', $duration)
                ->where('duration_type', $durationType)
                ->where('status', 'available')
                ->lockForUpdate()
                ->limit($count)
                ->get();

            if ($items->count() < $count) {
                throw new \Exception("Insufficient stock available for this duration (Required: {$count}, Available: {$items->count()})");
            }

            $results = [];
            foreach ($items as $item) {
                $item->update([
                    'status' => 'sold',
                    'sold_at' => now(),
                    'sold_to_user_id' => auth()->id() ?? null,
                ]);

                $results[] = [
                    'key' => $item->license_key,
                    'expires_at' => now()->add($duration, $durationType),
                ];
            }

            return $results;
        });
    }
}
