<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount_paid' => (string) $this->amount_paid,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'product' => [
                'id' => $this->product?->id,
                'name' => $this->product?->name ?? 'Unknown Product',
                'image_url' => $this->product?->image_url,
            ],
        ];
    }
}
