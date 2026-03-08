<?php

namespace App\Models;

use App\Mail\PurchaseReceiptMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class Purchase extends Model
{
    /**
     * Ensure that model hooks (like 'created') only fire after the transaction is fully committed.
     * This prevents duplicate emails if multiple processes hit simultaneously.
     */
    public $afterCommit = true;

    protected $fillable = [
        'order_id',
        'user_id',
        'product_id',
        'amount_paid',
        'payment_method',
        'status',
        'license_key',
        'checkout_order_id',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::creating(function ($purchase) {
            $purchase->order_id = 'RLBmods-' . (string) \Illuminate\Support\Str::uuid();
        });

        static::created(function ($purchase) {
            // Always send a receipt email after a purchase is created
            // Wrap in try-catch to prevent mail server issues from failing the payment IPN
            try {
                if ($purchase->user) {
                    \Illuminate\Support\Facades\Mail::to($purchase->user->email)->send(new \App\Mail\PurchaseReceiptMail($purchase));
                }
            } catch (\Throwable $e) {
                \Log::error('Failed to send purchase receipt email: ' . $e->getMessage(), [
                    'purchase_id' => $purchase->id,
                    'user_id' => $purchase->user_id
                ]);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
