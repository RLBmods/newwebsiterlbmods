<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SecurityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;

class IpVerificationController extends Controller
{
    /**
     * Show the IP verification view.
     */
    public function show(Request $request)
    {
        if (!$request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/VerifyIP', [
            'status' => session('status'),
        ]);
    }

    /**
     * Verify the OTP and login the user.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $userId = $request->session()->get('login.id');
        $user = User::findOrFail($userId);

        if ($user->otp_code !== $request->code || Carbon::now()->gt($user->otp_expires_at)) {
            SecurityLogger::log('failed_ip_verification', [
                'ip' => $request->session()->get('login.ip_to_verify'),
            ], $user->id);

            return back()->withErrors(['code' => 'Invalid or expired verification code.']);
        }

        // Clear OTP and add IP to known IPs
        $ip = $request->session()->get('login.ip_to_verify');
        $knownIps = $user->known_ips ?? [];
        
        $user->update([
            'otp_code' => null,
            'otp_expires_at' => null,
            'last_login_ip' => $ip,
            'known_ips' => array_unique(array_merge($knownIps, [$ip])),
        ]);

        // Log the user in
        Auth::login($user, $request->session()->get('login.remember', false));

        // Log successful IP verification and login
        SecurityLogger::log('ip_verified_login', ['ip' => $ip], $user->id);

        // Clear session
        $request->session()->forget(['login.id', 'login.remember', 'login.ip_to_verify']);

        return app(LoginResponse::class)->toResponse($request);
    }
}
