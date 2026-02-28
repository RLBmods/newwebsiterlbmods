<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadKey extends Model
{
    protected $fillable = [
        'key_value',
        'user_id',
        'product_id',
        'file_url',
        'expiration_time',
        'used_at',
        'last_download_at',
        'status',
        'ip_address',
        'user_agent',
        'download_count',
    ];

    protected function casts(): array
    {
        return [
            'expiration_time' => 'datetime',
            'used_at' => 'datetime',
            'last_download_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function isExpired(): bool
    {
        return $this->expiration_time->isPast();
    }

    public function isUsed(): bool
    {
        return $this->status === 'used';
    }
}
