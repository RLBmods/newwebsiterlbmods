<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    //
    public function index()
    {
        $user = auth()->user();

        $stats = [
            'total_users' => \App\Models\User::count(),
            'total_downloads' => \App\Models\DownloadKey::where('user_id', $user->id)->count(),
            'total_products' => \App\Models\Product::count(),
            'order_status' => \App\Models\Purchase::where('user_id', $user->id)->latest()->first()?->status ?? 'None',
        ];

        $recentOrders = \App\Http\Resources\PurchaseResource::collection(
            \App\Models\Purchase::with('product')
                ->where('user_id', $user->id)
                ->latest()
                ->take(5)
                ->get()
                ->values()
        );

        return inertia('Dashboard', [
            'stats' => $stats,
            'recentOrders' => $recentOrders->resolve(),
        ]);
    }
}
