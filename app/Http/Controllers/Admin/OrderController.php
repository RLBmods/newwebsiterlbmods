<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Purchase::with(['user', 'product'])
            ->latest()
            ->paginate(20)
            ->through(fn ($purchase) => [
                'id' => $purchase->id,
                'user_name' => $purchase->user?->name ?? 'Unknown',
                'product_name' => $purchase->product?->name ?? 'Unknown',
                'amount' => (float) $purchase->amount_paid,
                'status' => $purchase->status,
                'payment_method' => $purchase->payment_method,
                'created_at' => $purchase->created_at->format('M d, Y H:i'),
            ]);

        return Inertia::render('Admin/Orders', [
            'orders' => $orders,
        ]);
    }
}
