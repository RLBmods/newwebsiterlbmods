<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Support\Str;

class TestProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $product = Product::updateOrCreate(
            ['name' => 'testproduct 1'],
            [
                'description' => 'A simulated product for testing stock logic.',
                'price' => 10.00,
                'type' => 'stock',
                'status' => true,
                'reseller_can_sell' => true,
            ]
        );

        for ($i = 0; $i < 10; $i++) {
            ProductStock::create([
                'product_id' => $product->id,
                'license_key' => 'TEST-' . Str::upper(Str::random(4)) . '-' . Str::upper(Str::random(4)) . '-' . Str::upper(Str::random(4)),
                'duration' => 1,
                'duration_type' => 'days',
                'status' => 'available',
            ]);
        }
    }
}
