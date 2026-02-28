<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Resources\ProductResource;

class StockController extends Controller
{
    public function index()
    {
        $products = ProductResource::collection(
            Product::where('type', 'stock')
                ->with(['prices' => function($query) {
                    $query->orderBy('duration', 'asc');
                }])
                ->get()
        );

        return Inertia::render('Admin/StockManagement', [
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'duration' => 'required|integer',
            'duration_type' => 'required|string',
            'keysText' => 'required|string',
        ]);

        $keys = preg_split('/\r\n|\r|\n/', $validated['keysText']);
        $keys = array_filter(array_map('trim', $keys));

        $count = 0;
        foreach ($keys as $key) {
            // Remove line numbers if present (e.g., "1. KEY", "2) KEY")
            $cleanKey = preg_replace('/^\d+[\.\)]\s*/', '', $key);
            
            if (!empty($cleanKey)) {
                ProductStock::create([
                    'product_id' => $validated['product_id'],
                    'license_key' => $cleanKey,
                    'duration' => $validated['duration'],
                    'duration_type' => $validated['duration_type'],
                    'status' => 'available',
                ]);
                $count++;
            }
        }

        return back()->with('success', "Successfully imported {$count} keys.");
    }
}
