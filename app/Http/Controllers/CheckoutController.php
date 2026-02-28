<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\CheckoutOrder;
use App\Models\User;
use App\Services\PaytabsService;
use App\Services\NowPaymentsService;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    /**
     * Display the checkout page.
     */
    public function index()
    {
        return Inertia::render('Public/Checkout');
    }

    /**
     * Process the checkout order.
     */
    public function process(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.productId' => 'required|exists:products,id',
            'items.*.priceId' => 'required|exists:product_prices,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = auth()->user();
        $items = $request->items;
        $totalCost = 0;
        $currency = config('paytabs.currency', 'USD');

        // Calculate total cost and verify availability
        foreach ($items as $item) {
            $price = ProductPrice::findOrFail($item['priceId']);
            $totalCost += $price->price * $item['quantity'];
        }

        if ($user->balance < $totalCost) {
            return back()->withErrors(['message' => 'Insufficient balance. Please top up your account.']);
        }

        // Deduct balance
        $user->decrement('balance', $totalCost);

        foreach ($items as $item) {
            $product = Product::findOrFail($item['productId']);
            $price = ProductPrice::findOrFail($item['priceId']);

            // Create purchase record
            Purchase::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'amount_paid' => $price->price * $item['quantity'],
                'payment_method' => 'balance',
                'status' => 'completed',
            ]);
        }

        return redirect()->route('purchases.index')->with('success', 'Order processed successfully!');
    }

    /**
     * Initiate a PayTabs payment (card / Apple Pay / Google Pay).
     */
    public function paytabs(Request $request, PaytabsService $paytabs)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.productId' => 'required|exists:products,id',
            'items.*.priceId' => 'required|exists:product_prices,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if (! auth()->check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = auth()->user();
        $items = $request->items;
        $totalCost = 0;

        $lineItems = [];

        foreach ($items as $item) {
            $product = Product::findOrFail($item['productId']);
            $price = ProductPrice::findOrFail($item['priceId']);
            $amount = $price->price * $item['quantity'];

            $totalCost += $amount;

            $lineItems[] = [
                'product_id' => $product->id,
                'price_id' => $price->id,
                'quantity' => $item['quantity'],
                'amount' => $amount,
            ];
        }

        if ($totalCost <= 0) {
            return response()->json(['message' => 'Invalid total amount'], 422);
        }

        try {
            $orderRef = 'PT_' . Str::lower(Str::random(10)) . '_' . now()->timestamp;
            $amount = (float) number_format($totalCost, 2, '.', '');

            // Create order first so Webhooks can find it by order_ref
            $checkoutOrder = CheckoutOrder::create([
                'user_id' => $user->id,
                'provider' => 'paytabs',
                'status' => 'pending',
                'order_ref' => $orderRef,
                'total_amount' => $amount,
                'currency' => config('paytabs.currency', 'USD'),
                'items' => $lineItems,
            ]);

            $payload = [
                'profile_id' => (int) config('paytabs.profile_id'),
                'tran_type' => 'sale',
                'tran_class' => 'ecom',
                'cart_id' => (string) $orderRef,
                'cart_description' => 'RLBmods order for ' . $user->email,
                'cart_currency' => $checkoutOrder->currency,
                'cart_amount' => $checkoutOrder->total_amount,
                'customer_details' => [
                    'name' => (string) $user->name,
                    'email' => (string) $user->email,
                    'street1' => 'Digital Goods',
                    'city' => 'Dubai',
                    'state' => 'DU',
                    'country' => 'AE',
                    'zip' => '00000',
                    'ip' => (string) $request->ip(),
                ],
                'return' => route('checkout.paytabs.complete'),
            ];

            if (config('paytabs.callback_url')) {
                $payload['callback'] = config('paytabs.callback_url');
            }

            \Log::info('Initiating PayTabs payment', ['user_id' => $user->id, 'amount' => $amount, 'order_ref' => $orderRef]);
            $data = $paytabs->createPayment($payload);

            // Update with external info
            $checkoutOrder->update([
                'external_id' => (string) ($data['tran_ref'] ?? ''),
                'external_url' => (string) ($data['redirect_url'] ?? ''),
                'meta' => [
                    'paytabs' => $data
                ]
            ]);

            // Store checkout context in session as well for convenience
            session([
                'checkout_paytabs' => [
                    'order_ref' => $orderRef,
                    'tran_ref' => $data['tran_ref'] ?? null,
                ],
            ]);

            return Inertia::location($data['redirect_url']);
        } catch (\Throwable $e) {
            \Log::error('PayTabs Initiation Error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'order_ref' => $orderRef ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['message' => 'Failed to initiate PayTabs payment: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle PayTabs return URL after payment completion.
     */
    public function paytabsComplete(Request $request, PaytabsService $paytabs)
    {
        // PayTabs usually sends 'tranRef' or 'tran_ref' in the return POST/GET
        $tranRef = $request->input('tranRef') ?? $request->input('tran_ref');
        $sessionData = session('checkout_paytabs');

        // Fallback to session if request parameter is missing
        if (! $tranRef && $sessionData) {
            $tranRef = $sessionData['tran_ref'] ?? null;
        }

        if (! $tranRef) {
            \Log::warning('PayTabs redirect reached without tranRef', ['all' => $request->all(), 'session' => $sessionData]);
            return redirect()->route('checkout')->withErrors(['message' => 'Missing payment reference.']);
        }

        // Find the order by its external reference (tran_ref)
        $checkoutOrder = CheckoutOrder::where('external_id', (string) $tranRef)->first();

        // If not found by external_id, try by the cart_id (which we store in order_ref) if provided
        if (! $checkoutOrder && $request->has('cartId')) {
             $checkoutOrder = CheckoutOrder::where('order_ref', $request->input('cartId'))->first();
        }

        if (! $checkoutOrder) {
            \Log::error('PayTabs Order Not Found', ['tranRef' => $tranRef, 'all' => $request->all()]);
            return redirect()->route('checkout')->withErrors(['message' => 'Order not found for this payment.']);
        }

        // Idempotency: if already processed (maybe by IPN/webhook already), just redirect
        if ($checkoutOrder->processed_at) {
            session()->forget('checkout_paytabs');
            return redirect()->route('purchases.index')->with('success', 'Your order has been completed.');
        }

        try {
            $result = $paytabs->verifyPayment($tranRef);
        } catch (\Throwable $e) {
            \Log::error('PayTabs Verification Failed on Redirect', ['tranRef' => $tranRef, 'error' => $e->getMessage()]);
            return redirect()->route('checkout')->withErrors(['message' => 'Failed to verify payment: '.$e->getMessage()]);
        }

        $status = $result['payment_result']['response_status'] ?? null;

        if ($status !== 'A') {
            \Log::warning('PayTabs Payment Not Successful', ['tranRef' => $tranRef, 'status' => $status, 'result' => $result]);
            return redirect()->route('checkout')->withErrors(['message' => 'Payment was not successful (Status: '.$status.').']);
        }

        // Process order (Create purchases)
        foreach ($checkoutOrder->items as $item) {
            $product = Product::find($item['product_id']);
            if (! $product) {
                continue;
            }

            Purchase::create([
                'user_id' => $checkoutOrder->user_id,
                'product_id' => $product->id,
                'amount_paid' => $item['amount'],
                'payment_method' => 'paytabs',
                'status' => 'completed',
            ]);
        }

        $checkoutOrder->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        session()->forget('checkout_paytabs');

        return redirect()->route('purchases.index')->with('success', 'Payment successful! Your order has been completed.');
    }

    /**
     * Initiate a NOWPayments invoice (crypto, user selects coin).
     */
    public function nowpayments(Request $request, NowPaymentsService $nowPayments)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.productId' => 'required|exists:products,id',
            'items.*.priceId' => 'required|exists:product_prices,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if (! auth()->check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = auth()->user();
        $items = $request->items;

        $totalCost = 0;
        $lineItems = [];

        foreach ($items as $item) {
            $product = Product::findOrFail($item['productId']);
            $price = ProductPrice::findOrFail($item['priceId']);

            $amount = $price->price * $item['quantity'];
            $totalCost += $amount;

            $lineItems[] = [
                'product_id' => $product->id,
                'price_id' => $price->id,
                'quantity' => (int) $item['quantity'],
                'amount' => (float) $amount,
            ];
        }

        if ($totalCost <= 0) {
            return response()->json(['message' => 'Invalid total amount'], 422);
        }

        try {
            $orderRef = 'NP_' . Str::lower(Str::random(10)) . '_' . now()->timestamp;
            $amount = (float) number_format($totalCost, 2, '.', '');

            $checkoutOrder = CheckoutOrder::create([
                'user_id' => $user->id,
                'provider' => 'nowpayments',
                'status' => 'pending',
                'order_ref' => $orderRef,
                'total_amount' => $amount,
                'currency' => config('nowpayments.price_currency', 'usd'),
                'items' => $lineItems,
            ]);

            $payload = [
                'price_amount' => $checkoutOrder->total_amount,
                'price_currency' => $checkoutOrder->currency,
                'order_id' => $checkoutOrder->order_ref,
                'order_description' => 'RLBmods order for ' . $user->email,
                'ipn_callback_url' => config('nowpayments.ipn_callback_url'),
                'success_url' => config('nowpayments.success_url'),
                'cancel_url' => config('nowpayments.cancel_url'),
            ];

            \Log::info('Initiating NOWPayments invoice', ['user_id' => $user->id, 'amount' => $amount]);
            $data = $nowPayments->createInvoice($payload);

            $checkoutOrder->update([
                'external_id' => (string) ($data['id'] ?? $data['invoice_id'] ?? ''),
                'external_url' => (string) ($data['invoice_url'] ?? ''),
                'meta' => [
                    'nowpayments' => $data,
                ],
            ]);

            return Inertia::location($data['invoice_url']);
        } catch (\Throwable $e) {
            \Log::error('NOWPayments Initiation Error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['message' => 'Failed to initiate NOWPayments payment. Please try again or contact support.']);
        }
    }

    /**
     * NOWPayments IPN webhook. Creates purchases once payment is completed.
     */
    public function nowpaymentsIpn(Request $request, NowPaymentsService $nowPayments)
    {
        if (! $nowPayments->validateIpnSignature($request)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $orderRef = $payload['order_id'] ?? $payload['orderId'] ?? null;
        $paymentStatus = $payload['payment_status'] ?? $payload['paymentStatus'] ?? null;

        if (! $orderRef) {
            return response()->json(['message' => 'Missing order_id'], 422);
        }

        $checkoutOrder = CheckoutOrder::where('order_ref', $orderRef)->first();
        if (! $checkoutOrder) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $mapped = $nowPayments->mapStatus($paymentStatus);

        // Always update status/meta
        $meta = $checkoutOrder->meta ?? [];
        $meta['nowpayments_ipn'] = $payload;

        $checkoutOrder->status = $mapped;
        $checkoutOrder->external_id = (string) ($payload['payment_id'] ?? $payload['invoice_id'] ?? $checkoutOrder->external_id);
        $checkoutOrder->meta = $meta;
        $checkoutOrder->save();

        // Idempotency: only process once
        if ($checkoutOrder->processed_at) {
            return response()->json(['ok' => true]);
        }

        if ($mapped !== 'completed') {
            return response()->json(['ok' => true]);
        }

        // Handle Top-up logic
        if (($checkoutOrder->meta['type'] ?? '') === 'topup') {
            $user = User::find($checkoutOrder->user_id);
            if ($user) {
                $user->increment('balance', $checkoutOrder->total_amount);
                
                \App\Models\Transaction::create([
                    'user_id' => $user->id,
                    'amount' => $checkoutOrder->total_amount,
                    'status' => 'completed',
                    'payment_method' => 'nowpayments',
                    'details' => 'Account top-up via ' . $checkoutOrder->provider,
                ]);

                \Log::info("User {$user->id} account topped up with {$checkoutOrder->total_amount}");
            }
        } else {
            // Standard product purchase logic
            foreach ($checkoutOrder->items as $item) {
                $product = Product::find($item['product_id']);
                if (! $product) {
                    continue;
                }

                Purchase::create([
                    'user_id' => $checkoutOrder->user_id,
                    'product_id' => $product->id,
                    'amount_paid' => $item['amount'],
                    'payment_method' => 'nowpayments',
                    'status' => 'completed',
                ]);
            }
        }

        $checkoutOrder->processed_at = now();
        $checkoutOrder->save();

        return response()->json(['ok' => true]);
    }

    /**
     * PayTabs IPN/Callback handler.
     */
    public function paytabsIpn(Request $request, PaytabsService $paytabs)
    {
        $payload = $request->all();
        \Log::info('PayTabs IPN Received', $payload);

        $tranRef = $payload['tran_ref'] ?? null;
        $cartId = $payload['cart_id'] ?? null;

        if (!$tranRef) {
            \Log::error('PayTabs IPN Error: Missing tran_ref');
            return response()->json(['message' => 'Missing tran_ref'], 422);
        }

        try {
            \Log::info('Verifying PayTabs payment', ['tran_ref' => $tranRef]);
            $result = $paytabs->verifyPayment($tranRef);
            
            $status = $result['payment_result']['response_status'] ?? null;
            if ($status !== 'A') {
                \Log::warning('PayTabs IPN: Payment not authorized', [
                    'tran_ref' => $tranRef, 
                    'status' => $status,
                    'full_result' => $result
                ]);
                return response()->json(['message' => 'Payment not authorized'], 400);
            }

            $checkoutOrder = CheckoutOrder::where('external_id', (string) $tranRef)
                ->orWhere('order_ref', (string) $cartId)
                ->first();

            if (!$checkoutOrder) {
                \Log::error('PayTabs IPN Error: Order not found in database', [
                    'tran_ref' => $tranRef, 
                    'cart_id' => $cartId,
                    'payload' => $payload
                ]);
                return response()->json(['message' => 'Order not found'], 404);
            }

            if ($checkoutOrder->processed_at) {
                \Log::info('PayTabs IPN: Order already processed', ['order_id' => $checkoutOrder->id]);
                return response()->json(['ok' => true]);
            }

            // Important: Use user from order if auth is null (IPN has no session)
            $userId = $checkoutOrder->user_id;

            foreach ($checkoutOrder->items as $item) {
                $product = Product::find($item['product_id']);
                if (!$product) {
                    \Log::warning('PayTabs IPN: Product not found during purchase creation', ['product_id' => $item['product_id']]);
                    continue;
                }

                Purchase::create([
                    'user_id' => $userId,
                    'product_id' => $product->id,
                    'amount_paid' => $item['amount'] ?? 0,
                    'payment_method' => 'paytabs',
                    'status' => 'completed',
                ]);
            }

            $checkoutOrder->update([
                'status' => 'completed',
                'processed_at' => now(),
                'external_id' => (string) $tranRef
            ]);

            \Log::info('PayTabs IPN: Order processed successfully', ['order_id' => $checkoutOrder->id]);
            
            // Send Notification (resiliently)
            try {
                $user = User::find($userId);
                if ($user) {
                    $user->notify(new \App\Notifications\GeneralNotification('Your payment of ' . $checkoutOrder->total_amount . ' ' . $checkoutOrder->currency . ' was successful!'));
                }
            } catch (\Throwable $ne) {
                \Log::error('Failed to send IPN notification: ' . $ne->getMessage());
            }

            return response()->json(['ok' => true]);

        } catch (\Throwable $e) {
            \Log::error('PayTabs IPN Exception: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'payload' => $payload,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Internal Server Error',
                'detail' => $e->getMessage()
            ], 500);
        }
    }
}
