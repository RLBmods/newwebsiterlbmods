<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ResellerController extends Controller
{
    /**
     * Display the reseller dashboard.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get total licenses owned by the reseller
        $totalLicenses = License::where('user_id', $user->id)->count();

        // Get active licenses (assuming active means not expired)
        $activeLicenses = License::where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->count();

        // Get recent sales/purchases for licenses
        $recentPurchases = Purchase::with('product')
            ->where('user_id', $user->id)
            ->where('payment_method', 'credits')
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Reseller/Dashboard', [
            'stats' => [
                'total_licenses' => $totalLicenses,
                'active_licenses' => $activeLicenses,
                'balance' => (float)$user->balance,
            ],
            'recentPurchases' => $recentPurchases,
        ]);
    }
}
