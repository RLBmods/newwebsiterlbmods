<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\License;
use App\Services\LicenseProviderService;
use App\Services\LicenseProviderServiceView;
use App\Notifications\OrderCompleted;
use App\Http\Resources\LicenseResource;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class LicenseController extends Controller
{
    protected $licenseService;
    protected $licenseViewService;

    public function __construct(
        LicenseProviderService $licenseService,
        LicenseProviderServiceView $licenseViewService
    ) {
        $this->licenseService = $licenseService;
        $this->licenseViewService = $licenseViewService;
    }

    /**
     * List products that have licenses for the user.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // If admin, show all products. If reseller, show products they've purchased licenses for.
        if ($user->role === 'admin') {
            $products = Product::all();
        } else {
            $productIds = License::where('user_id', $user->id)->distinct()->pluck('product_id');
            $products = Product::whereIn('id', $productIds)->get();
        }

        return Inertia::render('Licenses/Index', [
            'products' => ProductResource::collection($products->values())->resolve()
        ]);
    }

    /**
     * Show licenses for a specific product.
     */
    public function show(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $user = $request->user();

        // Security: If not admin, verify user owns licenses for this product
        if ($user->role !== 'admin') {
            $ownsLicenses = License::where('user_id', $user->id)->where('product_id', $id)->exists();
            if (!$ownsLicenses) {
                return redirect()->route('licenses.index')->withErrors(['message' => 'You do not have licenses for this product.']);
            }
        }

        try {
            $licenses = $this->licenseViewService->fetch(
                $product, 
                $user->role === 'admin' ? null : $user
            );

            return Inertia::render('Licenses/ViewLicenses', [
                'product' => (new ProductResource($product))->resolve(),
                'licenses' => LicenseResource::collection($licenses->values())->resolve(),
                'is_reseller' => $user->role === 'reseller' || $user->role === 'admin',
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['message' => 'Failed to fetch licenses: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle HWID reset request.
     */
    public function resetHwid(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'license_key' => 'required|string',
        ]);

        $user = $request->user();
        $product = Product::findOrFail($request->product_id);
        $licenseKey = $request->license_key;

        // Security: If reseller, ensure they own this license key
        if ($user->role !== 'admin') {
            $license = License::where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->where('license_key', $licenseKey)
                ->first();

            if (!$license) {
                return back()->withErrors(['message' => 'You do not own this license key or it does not exist in our records.']);
            }
        }

        try {
            $result = $this->licenseViewService->resetHwid($product, $licenseKey);

            if ($result['success']) {
                return back()->with('success', $result['message']);
            } else {
                return back()->withErrors(['message' => $result['message']]);
            }
        } catch (\Exception $e) {
            return back()->withErrors(['message' => 'Failed to reset HWID: ' . $e->getMessage()]);
        }
    }

    /**
     * Show the license creation page.
     */
    public function create()
    {
        $products = Product::where('status', true)->with('prices')->get();
        return Inertia::render('Licenses/Create', [
            'products' => ProductResource::collection($products->values())->resolve()
        ]);
    }

    /**
     * Store newly generated licenses.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'duration' => 'required|integer|min:1',
            'duration_type' => 'required|string|in:days,weeks,months,lifetime',
            'count' => 'required|integer|min:1|max:50',
        ]);

        $product = Product::findOrFail($request->product_id);
        
        // Find specific price for duration
        $priceRecord = \App\Models\ProductPrice::where([
            'product_id' => $product->id,
            'duration' => $request->duration,
            'duration_type' => $request->duration_type,
        ])->first();

        if (!$priceRecord) {
            return back()->withErrors(['message' => 'The selected duration is not available for this product.']);
        }

        $unitPrice = $priceRecord->price;
        $count = $request->count;
        $subtotal = $unitPrice * $count;

        try {
            return DB::transaction(function () use ($product, $request, $count, $subtotal, $unitPrice) {
                // Fetch user with lock to prevent race conditions on balance
                $user = \App\Models\User::where('id', auth()->id())->lockForUpdate()->first();

                if (!$user) {
                    throw new \Exception('User session lost or invalid.');
                }

                $discountPercentage = 0;
                $commissionAmount = 0;
                $parentReseller = null;

                // Tiered Discount Logic
                if ($user->role === 'admin') {
                    $discountPercentage = 100;
                } else {
                    // Check for custom reseller discount first
                    if ($user->reseller_discount !== null) {
                        $discountPercentage = (float) $user->reseller_discount;
                    } elseif ($user->role === 'reseller') {
                        if ($user->parent_id) {
                            // Sub-reseller logic: 25% base discount
                            $discountPercentage = 25;
                            
                            // Calculate commission for parent
                            $parentReseller = \App\Models\User::find($user->parent_id);
                            if ($parentReseller) {
                                $parentDiscount = $parentReseller->reseller_discount !== null 
                                    ? (float) $parentReseller->reseller_discount 
                                    : 40;
                                
                                $commissionPercentage = $parentDiscount - $discountPercentage;
                                if ($commissionPercentage > 0) {
                                    $commissionAmount = $subtotal * ($commissionPercentage / 100);
                                }
                            }
                        } else {
                            // Main reseller logic: 40% default (ignoring legacy tiered logic as per user request)
                            $discountPercentage = 40;
                        }
                    }
                }

                $totalPrice = $subtotal * (1 - ($discountPercentage / 100));

                // Check and deduct balance
                if ($totalPrice > 0) {
                    if ($user->balance < $totalPrice) {
                        throw new \Exception('Insufficient balance. Please top up your account.');
                    }
                    $user->decrement('balance', $totalPrice);
                    
                    // Create Transaction record for deduction
                    \App\Models\Transaction::create([
                        'user_id' => $user->id,
                        'amount' => -$totalPrice,
                        'status' => 'completed',
                        'payment_method' => 'credits',
                        'details' => "Purchase of {$count}x {$product->name} licenses (" . ($discountPercentage > 0 ? $discountPercentage . "% Discount applied" : "Full price") . ")",
                    ]);

                    // Payout commission to parent if applicable
                    if ($commissionAmount > 0 && $parentReseller) {
                        $parentReseller->increment('balance', $commissionAmount);
                        \App\Models\Transaction::create([
                            'user_id' => $parentReseller->id,
                            'amount' => $commissionAmount,
                            'status' => 'completed',
                            'payment_method' => 'credits',
                            'details' => "Commission from sub-reseller {$user->name} purchase of {$count}x {$product->name} licenses",
                        ]);
                    }
                }

                // Generate licenses via service
                // If this fails, the whole transaction (including balance deduction) rolls back
                $generatedKeys = $this->licenseService->generate(
                    $product, 
                    $request->duration, 
                    $request->duration_type, 
                    $count,
                    $user->name
                );

                $keys = [];
                foreach ($generatedKeys as $keyData) {
                    License::create([
                        'user_id' => $user->id,
                        'product_id' => $product->id,
                        'license_key' => $keyData['key'],
                        'duration' => $request->duration,
                        'duration_type' => $request->duration_type,
                        'expires_at' => $keyData['expires_at'],
                        'generated_by' => $user->name,
                        'status' => 'active',
                    ]);

                    Purchase::create([
                        'user_id' => $user->id,
                        'product_id' => $product->id,
                        'amount_paid' => $product->price,
                        'payment_method' => $user->role === 'reseller' ? 'credits' : 'admin_grant',
                        'status' => 'completed',
                    ]);

                    $keys[] = $keyData['key'];
                }

                // Update product_access for downloads
                $this->updateProductAccess($user, $product);

                // Notify User
                $user->notify(new OrderCompleted($product, $keys));

                return back()->with([
                    'success' => true,
                    'keys' => $keys,
                    'product_name' => $product->name
                ]);
            });
        } catch (\Exception $e) {
            // Transaction handles logical rollbacks (balance restoration) automatically
            return back()->withErrors(['message' => 'Failed to process purchase: ' . $e->getMessage()]);
        }
    }

    /**
     * Update user's product_access field to grant download permissions.
     */
    protected function updateProductAccess($user, $product)
    {
        $currentAccess = array_filter(array_map('trim', explode(',', $user->product_access ?? '')));
        
        // Add product name if not already present
        if (!in_array($product->name, $currentAccess)) {
            $currentAccess[] = $product->name;
            $user->product_access = implode(',', $currentAccess);
            $user->save();
        }
    }
}
