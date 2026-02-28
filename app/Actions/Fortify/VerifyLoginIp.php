<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\SuspiciousLoginOTP;
use App\Services\SecurityLogger;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class VerifyLoginIp
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  callable  $next
     * @return mixed
     */
    public function handle(Request $request, $next)
    {
        $user = User::where(Fortify::username(), $request->{Fortify::username()})->first();

        if ($user && Hash::check($request->password, $user->password) && !$user->banned) {
            $ip = $request->ip();
            $knownIps = $user->known_ips ?? [];

            if (!empty($knownIps) && !in_array($ip, $knownIps)) {
                // Generate and send OTP
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $user->update([
                    'otp_code' => $otp,
                    'otp_expires_at' => Carbon::now()->addMinutes(15),
                ]);

                $user->notify(new SuspiciousLoginOTP($otp, $ip));

                // Log the suspicious attempt
                SecurityLogger::log('suspicious_login_attempt', [
                    'ip' => $ip,
                ], $user->id);

                // Store user ID in session temporarily and redirect to IP verification page
                $request->session()->put('login.id', $user->id);
                $request->session()->put('login.remember', $request->filled('remember'));
                $request->session()->put('login.ip_to_verify', $ip);

                return redirect()->route('auth.verify-ip');
            }

            // Update user's last login IP if it's already known
            $user->update([
                'last_login_ip' => $ip,
                'known_ips' => array_unique(array_merge($knownIps, [$ip])),
            ]);
        }

        return $next($request);
    }
}
