<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', [App\Http\Controllers\PublicController::class, 'index'])->name('home');

Route::get('/auth/verify-ip', [App\Http\Controllers\Auth\IpVerificationController::class, 'show'])->name('auth.verify-ip');
Route::post('/auth/verify-ip', [App\Http\Controllers\Auth\IpVerificationController::class, 'verify']);

Route::get('/shop', [App\Http\Controllers\PublicController::class, 'shop'])->name('shop');
Route::get('/status', [App\Http\Controllers\PublicController::class, 'status'])->name('status');
Route::get('/products/{product:name}', [App\Http\Controllers\PublicController::class, 'show'])->name('products.show');
Route::get('/checkout', [App\Http\Controllers\CheckoutController::class, 'index'])->name('checkout');
Route::post('/checkout/process', [App\Http\Controllers\CheckoutController::class, 'process'])->name('checkout.process');
Route::post('/checkout/paytabs', [App\Http\Controllers\CheckoutController::class, 'paytabs'])->name('checkout.paytabs');
Route::match(['get', 'post'], '/checkout/paytabs/complete', [App\Http\Controllers\CheckoutController::class, 'paytabsComplete'])->name('checkout.paytabs.complete');
Route::post('/checkout/nowpayments', [App\Http\Controllers\CheckoutController::class, 'nowpayments'])->name('checkout.nowpayments');
Route::post('/webhooks/nowpayments', [App\Http\Controllers\CheckoutController::class, 'nowpaymentsIpn'])->name('webhooks.nowpayments');
Route::post('/webhooks/paytabs', [App\Http\Controllers\CheckoutController::class, 'paytabsIpn'])->name('webhooks.paytabs');

// Social Auth Routes
Route::get('auth/{provider}/redirect', [App\Http\Controllers\Auth\SocialController::class, 'redirect'])
    ->name('social.redirect');
Route::get('auth/{provider}/link', [App\Http\Controllers\Auth\SocialController::class, 'link'])
    ->middleware(['auth'])
    ->name('social.link');
Route::post('auth/{provider}/unlink', [App\Http\Controllers\Auth\SocialController::class, 'unlink'])
    ->middleware(['auth'])
    ->name('social.unlink');
Route::get('auth/{provider}/callback', [App\Http\Controllers\Auth\SocialController::class, 'callback'])
    ->name('social.callback');

// OTP Verification Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/email/verify', [App\Http\Controllers\Auth\OTPVerificationController::class, 'show'])
        ->name('verification.notice');
    Route::post('/email/verify', [App\Http\Controllers\Auth\OTPVerificationController::class, 'verify'])
        ->name('verification.verify.otp');
    Route::post('/email/verification-notification', [App\Http\Controllers\Auth\OTPVerificationController::class, 'resend'])
        ->middleware(['throttle:6,1'])
        ->name('verification.send');
});

Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/users', [App\Http\Controllers\Admin\UserController::class, 'index'])->name('admin.users.index');
    Route::put('/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::get('/products', [App\Http\Controllers\Admin\ProductController::class, 'index'])->name('admin.products.index');
    Route::post('/products', [App\Http\Controllers\Admin\ProductController::class, 'store'])->name('admin.products.store');
    Route::put('/products/{product}', [App\Http\Controllers\Admin\ProductController::class, 'update'])->name('admin.products.update');
    Route::delete('/products/{product}', [App\Http\Controllers\Admin\ProductController::class, 'destroy'])->name('admin.products.destroy');

    // Stock Management
    Route::get('/stock', [App\Http\Controllers\Admin\StockController::class, 'index'])->name('admin.stock.index');
    Route::post('/stock', [App\Http\Controllers\Admin\StockController::class, 'store'])->name('admin.stock.store');

    Route::get('/transactions', [App\Http\Controllers\Admin\TransactionController::class, 'index'])->name('admin.transactions.index');

    Route::get('/orders', [App\Http\Controllers\Admin\OrderController::class, 'index'])->name('admin.orders.index');

    // Site Settings
    Route::get('/settings', [App\Http\Controllers\Admin\SettingController::class, 'index'])->name('admin.settings.index');
    Route::post('/settings', [App\Http\Controllers\Admin\SettingController::class, 'update'])->name('admin.settings.update');

    // Download File Management
    Route::get('/download-files', [App\Http\Controllers\Admin\DownloadFileController::class, 'list'])->name('admin.download-files.list');
    Route::post('/download-files/upload', [App\Http\Controllers\Admin\DownloadFileController::class, 'upload'])->name('admin.download-files.upload');

    // Admin Ticket Management
    Route::get('/tickets', [App\Http\Controllers\Admin\TicketController::class, 'index'])->name('admin.tickets.index');
    Route::get('/tickets/{ticket}', [App\Http\Controllers\Admin\TicketController::class, 'show'])->name('admin.tickets.show');
    Route::post('/tickets/{ticket}/reply', [App\Http\Controllers\Admin\TicketController::class, 'reply'])->name('admin.tickets.reply');
    Route::patch('/tickets/{ticket}/status', [App\Http\Controllers\Admin\TicketController::class, 'updateStatus'])->name('admin.tickets.status');
});

Route::middleware(['auth', 'verified', 'role:reseller'])->prefix('reseller')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Reseller\ResellerController::class, 'index'])->name('reseller.dashboard');
    
    // Workspace Management
    Route::get('/workspace', [App\Http\Controllers\Reseller\WorkspaceController::class, 'index'])->name('reseller.workspace.index');
    Route::post('/workspace', [App\Http\Controllers\Reseller\WorkspaceController::class, 'store'])->name('reseller.workspace.store');
    Route::post('/workspace/switch/{workspace}', [App\Http\Controllers\Reseller\WorkspaceController::class, 'switch'])->name('reseller.workspace.switch');
    Route::delete('/workspace/{workspace}', [App\Http\Controllers\Reseller\WorkspaceController::class, 'destroy'])->name('reseller.workspace.destroy');
    Route::patch('/workspace/{workspace}/members/{member}', [App\Http\Controllers\Reseller\WorkspaceController::class, 'updateMember'])->name('reseller.workspace.members.update');
    Route::delete('/workspace/{workspace}/members/{member}', [App\Http\Controllers\Reseller\WorkspaceController::class, 'removeMember'])->name('reseller.workspace.members.destroy');

    // Invitations
    Route::post('/workspace/{workspace}/invitations', [App\Http\Controllers\Reseller\WorkspaceInvitationController::class, 'store'])->name('reseller.workspace.invitations.store');
    Route::delete('/workspace/invitations/{invitation}', [App\Http\Controllers\Reseller\WorkspaceInvitationController::class, 'destroy'])->name('reseller.workspace.invitations.destroy');

    // Top-up
    Route::post('/topup', [App\Http\Controllers\Reseller\ResellerTopupController::class, 'store'])->name('reseller.topup.store');
});

// Invitation Acceptance (publicly accessible by auth users)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/workspace/join/{token}', [App\Http\Controllers\Reseller\WorkspaceInvitationController::class, 'accept'])->name('reseller.workspace.invitations.accept');
});

// Support Ticket Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/api/upload', [App\Http\Controllers\FileUploadController::class, 'store'])->name('api.upload');
    Route::get('/support', [App\Http\Controllers\Support\TicketController::class, 'index'])->name('support.index');
    Route::post('/support', [App\Http\Controllers\Support\TicketController::class, 'store'])->name('support.store');
    Route::get('/support/{ticket}', [App\Http\Controllers\Support\TicketController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/reply', [App\Http\Controllers\Support\TicketController::class, 'reply'])->name('support.reply');

    // Notifications
    Route::get('/notifications', [App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');

    // Licenses
    Route::get('/licenses', [App\Http\Controllers\LicenseController::class, 'index'])->name('licenses.index');
    Route::get('/licenses/create', [App\Http\Controllers\LicenseController::class, 'create'])->name('licenses.create');
    Route::post('/licenses', [App\Http\Controllers\LicenseController::class, 'store'])->name('licenses.store');
    Route::post('/licenses/reset', [App\Http\Controllers\LicenseController::class, 'resetHwid'])->name('licenses.reset.post');
    Route::get('/licenses/{id}', [App\Http\Controllers\LicenseController::class, 'show'])->name('licenses.show');

    // Downloads
    Route::get('/downloads', [App\Http\Controllers\DownloadController::class, 'index'])->name('downloads.index');
    Route::post('/downloads/{product}/key', [App\Http\Controllers\DownloadController::class, 'generateKey'])->name('downloads.key');
    Route::get('/downloads/file/{key}', [App\Http\Controllers\DownloadController::class, 'download'])->name('downloads.file');

    // Purchases
    Route::get('/purchases', [App\Http\Controllers\PurchaseController::class, 'index'])->name('purchases.index');
});
use App\Notifications\GeneralNotification;

Route::get('/test-notify', function () {
    $user = auth()->user();
    
    // We send the notification class, not the model
    $user->notify(new GeneralNotification('Your payment of $10 was successful!'));
    
    return "Notification sent! Go check your bell.";
});
require __DIR__.'/settings.php';
