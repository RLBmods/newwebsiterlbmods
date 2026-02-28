<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with('user')->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search by transaction ID, user name, or payment method
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('payment_method', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $transactions = $query->paginate(20)
            ->through(fn ($txn) => [
                'id' => $txn->id,
                'user_name' => $txn->user?->name ?? 'Unknown',
                'user_email' => $txn->user?->email ?? 'Unknown',
                'amount' => (float) $txn->amount,
                'status' => $txn->status,
                'payment_method' => $txn->payment_method,
                'details' => $txn->details,
                'created_at' => $txn->created_at->format('M d, Y H:i'),
            ]);

        return Inertia::render('Admin/Transactions', [
            'transactions' => $transactions,
            'filters' => $request->only(['status', 'search']),
        ]);
    }
}
