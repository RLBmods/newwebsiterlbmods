<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\SecurityLogger;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class BlockBannedUsers
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

        if ($user && Hash::check($request->password, $user->password) && $user->banned) {
            SecurityLogger::log('banned_login_attempt', [
                'email' => $user->email,
            ], $user->id);

            throw ValidationException::withMessages([
                Fortify::username() => [__('Your account has been banned.')],
            ]);
        }

        return $next($request);
    }
}
