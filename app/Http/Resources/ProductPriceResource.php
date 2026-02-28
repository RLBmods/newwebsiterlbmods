<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductPriceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $product = $this->resource->product;
        $stock = 0;

        if ($product) {
            if ($product->type === 'stock') {
                $stock = \App\Models\ProductStock::where([
                    'product_id' => $this->product_id,
                    'duration' => $this->duration,
                    'duration_type' => $this->duration_type,
                    'status' => 'available'
                ])->count();
            } else {
                $stock = '10+';
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name ?? "{$this->duration} " . ucfirst($this->duration_type ?? 'day') . " Plan",
            'price' => (float) $this->price,
            'duration' => $this->duration,
            'duration_type' => $this->duration_type,
            'stock' => $stock,
        ];
    }
}
