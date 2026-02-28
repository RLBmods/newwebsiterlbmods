<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Resources\ProductResource;

class PublicController extends Controller
{
    public function index()
    {
        $featuredProducts = ProductResource::collection(
            \App\Models\Product::with('prices')->take(3)->get()->values()
        );

        return Inertia::render('Welcome', [
            'canRegister' => Features::enabled(Features::registration()),
            'featuredProducts' => $featuredProducts->resolve()
        ]);
    }

    public function shop()
    {
        $products = ProductResource::collection(
            \App\Models\Product::with('prices')->get()->values()
        );

        return Inertia::render('Public/Shop', [
            'products' => $products->resolve()
        ]);
    }

    public function status()
    {
        $products = \App\Models\Product::with('prices')->get();
        
        $statuses = $products->groupBy('game')->map(function ($items, $game) {
            return [
                'game' => $game ?: 'Other',
                'products' => ProductResource::collection($items->values())->resolve()
            ];
        })->values()->all();

        return Inertia::render('Public/Status', [
            'statuses' => $statuses
        ]);
    }

    public function show(\App\Models\Product $product)
    {
        $product->load(['prices' => function($query) {
            $query->orderBy('price', 'asc');
        }]);

        // Scan for carousel images
        $carouselImages = [];
        $imageDir = public_path("images/products/{$product->id}/img");
        
        if (file_exists($imageDir)) {
            $files = scandir($imageDir);
            foreach ($files as $file) {
                if (in_array(pathinfo($file, PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    $carouselImages[] = asset("images/products/{$product->id}/img/{$file}");
                }
            }
        }

        return Inertia::render('Public/ProductShow', [
            'product' => (new ProductResource($product))->resolve(),
            'carousel_images' => $carouselImages
        ]);
    }
}
