<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Resources\ProductResource;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('prices')->latest()->paginate(20);

        return Inertia::render('Admin/Products', [
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'type' => 'required|string',
            'category' => 'nullable|string',
            'game' => 'nullable|string',
            'public_status' => 'nullable|string',
            'version' => 'nullable|string',
            'tutorial_link' => 'nullable|string',
            'features' => 'nullable|array',
            'requirements' => 'nullable|array',
            'menu_images' => 'nullable|array',
            'spoofer_included' => 'nullable|boolean',
            'download_url' => 'nullable|string|max:1024',
            'prices' => 'nullable|array',
            'prices.*.duration' => 'required|integer',
            'prices.*.duration_type' => 'required|string',
            'prices.*.price' => 'required|numeric',
        ]);

        $product = Product::create(array_diff_key($validated, ['prices' => '']));

        if (!empty($validated['prices'])) {
            foreach ($validated['prices'] as $priceData) {
                $product->prices()->create($priceData);
            }
        }

        $product->load('prices');
        return back()->with('success', 'Product created successfully.');
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'type' => 'required|string',
            'category' => 'nullable|string',
            'game' => 'nullable|string',
            'public_status' => 'nullable|string',
            'version' => 'nullable|string',
            'tutorial_link' => 'nullable|string',
            'features' => 'nullable|array',
            'requirements' => 'nullable|array',
            'menu_images' => 'nullable|array',
            'spoofer_included' => 'nullable|boolean',
            'download_url' => 'nullable|string|max:1024',
            'prices' => 'nullable|array',
            'prices.*.duration' => 'required|integer',
            'prices.*.duration_type' => 'required|string|in:days,weeks,months,lifetime',
            'prices.*.price' => 'required|numeric',
        ]);

        $product->update(array_diff_key($validated, ['prices' => '']));

        // Simple sync for prices
        $product->prices()->delete();
        if (!empty($validated['prices'])) {
            foreach ($validated['prices'] as $priceData) {
                $product->prices()->create($priceData);
            }
        }

        $product->load('prices');
        return back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return back()->with('success', 'Product deleted successfully.');
    }
}
