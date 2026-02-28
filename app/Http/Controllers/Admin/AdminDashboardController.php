<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Http\Resources\UserResource;

class AdminDashboardController extends Controller
{
    public function index()
    {
        // Total revenue (all completed purchases)
        $totalRevenue = Purchase::where('status', 'completed')
            ->sum('amount_paid');

        // Revenue for last 30 days
        $revenueLast30Days = Purchase::where('status', 'completed')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->sum('amount_paid');

        // Revenue for last 7 days
        $revenueLast7Days = Purchase::where('status', 'completed')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->sum('amount_paid');

        // Revenue by day for last 30 days (for graph)
        $revenueByDay = Purchase::where('status', 'completed')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount_paid) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => Carbon::parse($item->date)->format('M d'),
                    'revenue' => (float) $item->revenue,
                ];
            });

        // Fill in missing days with 0
        $revenueChartData = [];
        $labels = [];
        $data = [];
        
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dateStr = $date->format('M d');
            $dateKey = $date->format('Y-m-d');
            
            $dayRevenue = $revenueByDay->firstWhere('date', $dateStr);
            $revenue = $dayRevenue ? $dayRevenue['revenue'] : 0;
            
            $labels[] = $dateStr;
            $data[] = $revenue;
        }

        // Total users
        $totalUsers = User::count();

        // New users in last 30 days
        $newUsersLast30Days = User::where('created_at', '>=', Carbon::now()->subDays(30))
            ->count();

        // New users in last 7 days
        $newUsersLast7Days = User::where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        // Latest users (last 10)
        $latestUsers = UserResource::collection(
            User::latest('created_at')->take(10)->get()->values()
        );

        // Total purchases
        $totalPurchases = Purchase::where('status', 'completed')->count();

        // Purchases in last 30 days
        $purchasesLast30Days = Purchase::where('status', 'completed')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->count();

        // Revenue by payment method
        $revenueByMethod = Purchase::where('status', 'completed')
            ->select('payment_method', DB::raw('SUM(amount_paid) as total'))
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) {
                return [
                    'method' => ucfirst(str_replace('_', ' ', $item->payment_method)),
                    'total' => (float) $item->total,
                ];
            });

        // Top products by revenue
        $topProducts = Purchase::where('status', 'completed')
            ->with('product:id,name')
            ->select('product_id', DB::raw('SUM(amount_paid) as revenue'), DB::raw('COUNT(*) as sales'))
            ->groupBy('product_id')
            ->orderBy('revenue', 'desc')
            ->take(5)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name ?? 'Unknown',
                    'revenue' => (float) $item->revenue,
                    'sales' => (int) $item->sales,
                ];
            });

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'totalRevenue' => (float) $totalRevenue,
                'revenueLast30Days' => (float) $revenueLast30Days,
                'revenueLast7Days' => (float) $revenueLast7Days,
                'totalUsers' => $totalUsers,
                'newUsersLast30Days' => $newUsersLast30Days,
                'newUsersLast7Days' => $newUsersLast7Days,
                'totalPurchases' => $totalPurchases,
                'purchasesLast30Days' => $purchasesLast30Days,
            ],
            'revenueChart' => [
                'labels' => $labels,
                'data' => $data,
            ],
            'latestUsers' => $latestUsers->resolve(),
            'revenueByMethod' => $revenueByMethod,
            'topProducts' => $topProducts,
        ]);
    }
}
