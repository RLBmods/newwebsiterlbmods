<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the user's purchases.
     */
    public function index()
    {
        $purchases = Purchase::with('product')
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases,
        ]);
    }
}
