<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\DownloadKey;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DownloadController extends Controller
{
    /**
     * Map product names to legacy access strings.
     */
    protected $productAccessMap = [
        'Temp Spoofer' => ['Temp Spoofer'],
        'Fortnite' => ['Fortnite - Public', 'Fortnite - Private'],
        'B07' => ['B07'],
        'Rust' => ['Rust'],
        'Apex' => ['Apex Legends'],
        'Valorant' => ['Valorant Color Bot', 'Valorant Full'],
        'Perm Spoofer' => ['Perm Spoofer']
    ];

    /**
     * Display a listing of accessible products.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $userAccess = array_filter(array_map('trim', explode(',', $user->product_access ?? '')));

        $products = Product::where('status', '!=', 5) // Not Offline
            ->get()
            ->filter(function ($product) use ($userAccess) {
                // Direct match
                if (in_array($product->name, $userAccess)) {
                    return true;
                }

                // Map match
                foreach ($this->productAccessMap as $key => $levels) {
                    if (Str::contains(Str::lower($product->name), Str::lower($key))) {
                        foreach ($levels as $level) {
                            if (in_array($level, $userAccess)) {
                                return true;
                            }
                        }
                    }
                }

                return false;
            })
            ->values();

        return Inertia::render('Downloads/Index', [
            'products' => $products
        ]);
    }

    /**
     * Generate a secure download key.
     */
    public function generateKey(Product $product, Request $request)
    {
        // 1. Verify access (simple check for now, can be expanded)
        $user = $request->user();
        $userAccess = array_filter(array_map('trim', explode(',', $user->product_access ?? '')));
        
        $hasAccess = in_array($product->name, $userAccess);
        if (!$hasAccess) {
            foreach ($this->productAccessMap as $key => $levels) {
                if (Str::contains(Str::lower($product->name), Str::lower($key))) {
                    foreach ($levels as $level) {
                        if (in_array($level, $userAccess)) {
                            $hasAccess = true;
                            break 2;
                        }
                    }
                }
            }
        }

        if (!$hasAccess) {
            return back()->with('error', "You don't have access to this product.");
        }

        if ($product->status == 5) {
            return back()->with('error', "This product is currently offline.");
        }

        // 2. Generate Key
        $downloadKey = DownloadKey::create([
            'key_value' => Str::random(32),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'file_url' => $product->download_url,
            'expiration_time' => now()->addMinutes(5),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'unused',
        ]);

        return response()->json([
            'success' => true,
            'download_url' => route('downloads.file', ['key' => $downloadKey->key_value])
        ]);
    }

    /**
     * Handle the actual file download.
     */
    public function download($key, Request $request)
    {
        $downloadKey = DownloadKey::where('key_value', $key)
            ->where('status', 'unused')
            ->where('expiration_time', '>', now())
            ->first();

        if (!$downloadKey) {
            return abort(403, 'Invalid or expired download key.');
        }

        if ($downloadKey->ip_address !== $request->ip()) {
            return abort(403, 'IP address mismatch.');
        }

        // Mark as used
        $downloadKey->update([
            'status' => 'used',
            'used_at' => now(),
            'last_download_at' => now(),
            'download_count' => $downloadKey->download_count + 1,
            'user_agent' => $request->userAgent(),
        ]);

        $url = $downloadKey->file_url;

        if (Str::startsWith($url, 'http')) {
            return redirect()->away($url);
        }

        // Local file serving
        $path = storage_path('app/public/downloads/' . basename($url));
        if (!file_exists($path)) {
            return abort(404, 'File not found on server.');
        }

        return response()->download($path);
    }
}
