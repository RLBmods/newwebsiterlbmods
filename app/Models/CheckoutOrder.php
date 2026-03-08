<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutOrder extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'status',
        'order_ref',
        'total_amount',
        'currency',
        'items',
        'external_id',
        'external_url',
        'meta',
        'processed_at',
    ];

    protected $casts = [
        'items' => 'array',
        'meta' => 'array',
        'total_amount' => 'float',
        'processed_at' => 'datetime',
    ];
}

