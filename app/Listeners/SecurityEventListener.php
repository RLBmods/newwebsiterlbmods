<?php

namespace App\Listeners;

use App\Services\SecurityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;

class SecurityEventListener
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        if ($event instanceof Login) {
            SecurityLogger::logLogin($event->user->id);
        } elseif ($event instanceof Failed) {
            SecurityLogger::logFailedLogin(
                $event->credentials['email'] ?? 'unknown',
                'Invalid credentials'
            );
        } elseif ($event instanceof Logout) {
            if ($event->user) {
                SecurityLogger::logLogout($event->user->id);
            }
        } elseif ($event instanceof PasswordReset) {
            SecurityLogger::log('password_reset', null, $event->user->id);
        } elseif ($event instanceof Verified) {
            SecurityLogger::log('email_verified', null, $event->user->id);
        }
    }
}
