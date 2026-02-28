<?php

namespace App\Services;

use App\Models\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SecurityLogger
{
    /**
     * Log a security event.
     *
     * @param string $event
     * @param array|null $details
     * @param int|null $userId
     * @return void
     */
    public static function log(string $event, ?array $details = null, ?int $userId = null): void
    {
        SecurityLog::create([
            'user_id' => $userId ?? Auth::id(),
            'event' => $event,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'details' => $details,
        ]);
    }

    /**
     * Log a failed login attempt.
     */
    public static function logFailedLogin(string $email, string $reason): void
    {
        self::log('failed_login', [
            'email' => $email,
            'reason' => $reason,
        ]);
    }

    /**
     * Log a successful login.
     */
    public static function logLogin(int $userId): void
    {
        self::log('login', null, $userId);
    }

    /**
     * Log a logout event.
     */
    public static function logLogout(int $userId): void
    {
        self::log('logout', null, $userId);
    }
}
