<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'price',
        'image_url',
        'description',
        'file_name',
        'download_url',
        'type',
        'category',
        'game',
        'status',
        'public_status',
        'api_url',
        'api_key',
        'license_identifier',
        'license_level',
        'version',
        'reseller_can_sell',
        'reseller_file_name',
        'tutorial_link',
        'features',
        'requirements',
        'menu_images',
        'spoofer_included',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'status' => 'integer',
            'reseller_can_sell' => 'boolean',
            'license_level' => 'integer',
            'features' => 'array',
            'requirements' => 'array',
            'menu_images' => 'array',
            'spoofer_included' => 'boolean',
        ];
    }

    /**
     * Get the prices for the product.
     */
    public function prices()
    {
        return $this->hasMany(ProductPrice::class);
    }
}
