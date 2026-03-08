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
use App\Models\Transaction;
use App\Notifications\GeneralNotification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    protected $licenseProvider;

    public function __construct(\App\Services\LicenseProviderService $licenseProvider)
    {
        $this->licenseProvider = $licenseProvider;
    }

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

        // Use a transaction and lock the order to prevent duplicate processing (idempotency)
        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($tranRef, $paytabs) {
                // Find and lock the order
                $checkoutOrder = CheckoutOrder::where('external_id', (string) $tranRef)->lockForUpdate()->first();

                if (! $checkoutOrder) {
                    Log::error('PayTabs Order Not Found during lock', ['tranRef' => $tranRef]);
                    return redirect()->route('checkout')->withErrors(['message' => 'Order not found for this payment.']);
                }

                // Idempotency: if already processed, skip duplication
                if ($checkoutOrder->processed_at) {
                    session()->forget('checkout_paytabs');
                    session()->flash('clear_cart', true);
                    return redirect()->route('purchases.index')->with('success', 'Your order has been completed.');
                }

                $result = $paytabs->verifyPayment($tranRef);
                $status = $result['payment_result']['response_status'] ?? null;

                if ($status !== 'A') {
                    Log::warning('PayTabs Payment Not Successful', ['tranRef' => $tranRef, 'status' => $status]);
                    return redirect()->route('checkout')->withErrors(['message' => 'Payment was not successful.']);
                }

                // Process order (Create purchases)
                foreach ($checkoutOrder->items as $item) {
                    $product = Product::find($item['product_id']);
                    if (! $product) {
                        Log::warning('Product not found in PayTabs completion', ['product_id' => $item['product_id']]);
                        continue;
                    }

                    // Strict Idempotency: skip if purchase already exists for this order/product
                    $exists = Purchase::where('checkout_order_id', $checkoutOrder->id)
                        ->where('product_id', $product->id)
                        ->exists();
                    
                    if ($exists) {
                        Log::info('Purchase already exists for this order item', ['order' => $checkoutOrder->id, 'product' => $product->id]);
                        continue;
                    }

                    $purchase = Purchase::create([
                        'user_id' => $checkoutOrder->user_id,
                        'product_id' => $product->id,
                        'amount_paid' => $item['amount'] ?? 0,
                        'payment_method' => 'paytabs',
                        'status' => 'completed',
                        'checkout_order_id' => $checkoutOrder->id,
                    ]);

                    // Generate License Key
                    $this->triggerLicenseGeneration($purchase, $item, $checkoutOrder);
                }

                $checkoutOrder->update([
                    'status' => 'completed',
                    'processed_at' => now(),
                ]);

                session()->forget('checkout_paytabs');
                session()->flash('clear_cart', true);
                
                Log::info('PayTabs Return: Order processed and redirecting to purchases', ['order_id' => $checkoutOrder->id]);
                return redirect()->route('purchases.index')->with('success', 'Payment successful!');
            });
        } catch (\Throwable $e) {
            Log::error('PayTabs Completion Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            // Still try to clear the cart if we suspect the payment might have actually gone through
            session()->flash('clear_cart', true);
            return redirect()->route('purchases.index')->with('success', 'Your order is being processed.');
        }
    }

    /**
     * Helper to handle license generation logic.
     */
    protected function triggerLicenseGeneration($purchase, $item, $checkoutOrder)
    {
        try {
            if (isset($item['price_id'])) {
                $priceRecord = ProductPrice::find($item['price_id']);
                if ($priceRecord) {
                    $keys = $this->licenseProvider->generate(
                        $purchase->product,
                        $priceRecord->duration,
                        $priceRecord->duration_type,
                        1,
                        $checkoutOrder->user->name ?? 'System'
                    );

                    if (!empty($keys)) {
                        $keyData = $keys[0];
                        \App\Models\License::create([
                            'user_id' => $checkoutOrder->user_id,
                            'product_id' => $purchase->product_id,
                            'license_key' => $keyData['key'],
                            'duration' => $priceRecord->duration,
                            'duration_type' => $priceRecord->duration_type,
                            'expires_at' => $keyData['expires_at'],
                            'generated_by' => 'Auto-Checkout',
                            'status' => 'active',
                        ]);
                        $purchase->update(['license_key' => $keyData['key']]);
                        $this->grantProductAccess($checkoutOrder->user, $purchase->product);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Auto License Generation Failed: ' . $e->getMessage());
        }
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

        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($payload, $orderRef, $paymentStatus, $nowPayments) {
                $checkoutOrder = CheckoutOrder::where('order_ref', $orderRef)->lockForUpdate()->first();
                if (! $checkoutOrder) {
                    return response()->json(['message' => 'Order not found'], 404);
                }

                $mapped = $nowPayments->mapStatus($paymentStatus);

                // Always update status/meta
                $meta = $checkoutOrder->meta ?? [];
                $meta['nowpayments_ipn_latest'] = $payload;

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

                        // Strict Idempotency: check if purchase already exists
                        $exists = Purchase::where('checkout_order_id', $checkoutOrder->id)
                            ->where('product_id', $product->id)
                            ->exists();
                        
                        if ($exists) {
                            continue;
                        }

                        $purchase = Purchase::create([
                            'user_id' => $checkoutOrder->user_id,
                            'product_id' => $product->id,
                            'amount_paid' => $item['amount'] ?? 0,
                            'payment_method' => 'nowpayments',
                            'status' => 'completed',
                            'checkout_order_id' => $checkoutOrder->id,
                        ]);

                        $this->triggerLicenseGeneration($purchase, $item, $checkoutOrder);
                    }
                }

                $checkoutOrder->processed_at = now();
                $checkoutOrder->save();

                return response()->json(['ok' => true]);
            });
        } catch (\Throwable $e) {
            Log::error('NOWPayments IPN Exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PayTabs IPN/Callback handler.
     */
    public function paytabsIpn(Request $request, PaytabsService $paytabs)
    {
        $payload = $request->all();
        Log::info('PayTabs IPN Received', $payload);

        $tranRef = $payload['tran_ref'] ?? null;
        $cartId = $payload['cart_id'] ?? null;

        if (!$tranRef) {
            \Log::error('PayTabs IPN Error: Missing tran_ref');
            return response()->json(['message' => 'Missing tran_ref'], 422);
        }

        try {
            \Log::info('Verifying PayTabs payment', ['tran_ref' => $tranRef]);

            return \Illuminate\Support\Facades\DB::transaction(function () use ($tranRef, $cartId, $paytabs, $payload) {
                $checkoutOrder = CheckoutOrder::where('external_id', (string) $tranRef)
                    ->orWhere('order_ref', (string) $cartId)
                    ->lockForUpdate()
                    ->first();

                if (!$checkoutOrder) {
                    Log::error('PayTabs IPN Error: Order not found');
                    return response()->json(['message' => 'Order not found'], 404);
                }

                if ($checkoutOrder->processed_at) {
                    return response()->json(['ok' => true]);
                }

                $result = $paytabs->verifyPayment($tranRef);
                $status = $result['payment_result']['response_status'] ?? null;
                
                if ($status !== 'A') {
                    return response()->json(['message' => 'Not authorized'], 400);
                }

                foreach ($checkoutOrder->items as $item) {
                    $product = Product::find($item['product_id']);
                    if (!$product) continue;

                    // Strict Idempotency: check if purchase already exists
                    $exists = Purchase::where('checkout_order_id', $checkoutOrder->id)
                        ->where('product_id', $product->id)
                        ->exists();
                    
                    if ($exists) {
                        continue;
                    }

                    $purchase = Purchase::create([
                        'user_id' => $checkoutOrder->user_id,
                        'product_id' => $product->id,
                        'amount_paid' => $item['amount'] ?? 0,
                        'payment_method' => 'paytabs',
                        'status' => 'completed',
                        'checkout_order_id' => $checkoutOrder->id,
                    ]);

                    $this->triggerLicenseGeneration($purchase, $item, $checkoutOrder);
                }

                $checkoutOrder->update([
                    'status' => 'completed',
                    'processed_at' => now(),
                ]);

                // Send Notification
                try {
                    $user = User::find($checkoutOrder->user_id);
                    if ($user) {
                        $user->notify(new \App\Notifications\GeneralNotification('Your payment of ' . $checkoutOrder->total_amount . ' ' . $checkoutOrder->currency . ' was successful!'));
                    }
                } catch (\Throwable $ne) {}

                return response()->json(['ok' => true]);
            });

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
