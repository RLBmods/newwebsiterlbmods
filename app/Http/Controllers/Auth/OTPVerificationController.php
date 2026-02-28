<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OTPVerificationController extends Controller
{
    /**
     * Show the verification page.
     */
    public function show(Request $request)
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->intended(config('fortify.home'))
            : Inertia::render('auth/VerifyEmail', [
                'status' => session('status'),
            ]);
    }

    /**
     * Verify the OTP.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(config('fortify.home'));
        }

        if ($request->code === $user->otp_code && $user->otp_expires_at->isFuture()) {
            $user->markEmailAsVerified();
            $user->otp_code = null;
            $user->otp_expires_at = null;
            $user->save();

            return redirect()->intended(config('fortify.home'))->with('status', 'email-verified');
        }

        return back()->withErrors(['code' => 'The verification code is invalid or has expired.']);
    }

    /**
     * Resend the verification OTP.
     */
    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(config('fortify.home'));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
