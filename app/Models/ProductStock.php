<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    protected $fillable = [
        'product_id',
        'license_key',
        'duration',
        'duration_type',
        'status',
        'sold_at',
        'sold_to_user_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
