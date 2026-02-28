<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) ($this->prices->min('price') ?? $this->price ?? 0),
            'game' => $this->game ?? 'Other',
            'category' => $this->category ?? 'General',
            'status' => $this->public_status ?? 'Undetected',
            'image' => $this->image_url ?? $this->image ?? '/images/products/placeholder.webp',
            'features' => $this->features ?? [],
            'requirements' => $this->requirements ?? [],
            'anti_cheat' => $this->anti_cheat ?? 'Vanguard',
            'spoofer_included' => (bool) $this->spoofer_included,
            'is_visible' => (bool) $this->status,
            'prices' => $this->relationLoaded('prices') 
                ? ProductPriceResource::collection($this->prices)->resolve() 
                : [],
        ];
    }
}
