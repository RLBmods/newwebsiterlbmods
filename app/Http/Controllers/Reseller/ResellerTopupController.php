<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\CheckoutOrder;
use App\Services\NowPaymentsService;
use Illuminate\Support\Str;

class ResellerTopupController extends Controller
{
    /**
     * Store a new top-up request and redirect to NOWPayments.
     */
    public function store(Request $request, NowPaymentsService $nowPayments)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $user = $request->user();
        $amount = (float) $request->amount;

        $orderRef = 'TOPUP_' . Str::lower(Str::random(10)) . '_' . now()->timestamp;

        $checkoutOrder = CheckoutOrder::create([
            'user_id' => $user->id,
            'provider' => 'nowpayments',
            'status' => 'pending',
            'order_ref' => $orderRef,
            'total_amount' => round($amount, 2),
            'currency' => config('nowpayments.price_currency', 'usd'),
            'meta' => [
                'type' => 'topup',
            ],
            'items' => [], // Empty for top-up
        ]);

        $payload = [
            'price_amount' => $checkoutOrder->total_amount,
            'price_currency' => $checkoutOrder->currency,
            'order_id' => $checkoutOrder->order_ref,
            'order_description' => 'Account Top-up for ' . $user->email,
            'ipn_callback_url' => config('nowpayments.ipn_callback_url'),
            'success_url' => route('reseller.dashboard'),
            'cancel_url' => route('reseller.dashboard'),
        ];

        try {
            $data = $nowPayments->createInvoice($payload);

            $checkoutOrder->update([
                'external_id' => (string) ($data['id'] ?? $data['invoice_id'] ?? ''),
                'external_url' => (string) ($data['invoice_url'] ?? ''),
                'meta' => array_merge($checkoutOrder->meta ?? [], ['nowpayments' => $data]),
            ]);

            return response()->json([
                'redirectUrl' => $data['invoice_url'],
                'orderRef' => $checkoutOrder->order_ref,
            ]);
        } catch (\Exception $e) {
            \Log::error('Reseller Topup Error: ' . $e->getMessage(), [
                'user_id' => $user->id,
            ]);
            return response()->json(['message' => 'Failed to create payment: ' . $e->getMessage()], 500);
        }
    }
}
