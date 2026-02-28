<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class SocialController extends Controller
{
    public function redirect(string $provider)
    {
        $redirect = request()->query('redirect');
        if ($redirect && $this->isSafeRedirect($redirect)) {
            session(['auth_redirect' => $redirect]);
        }
        return Socialite::driver($provider)->redirect();
    }

    private function isSafeRedirect(string $url): bool
    {
        $url = trim($url);
        return $url !== '' && $url[0] === '/' && ! str_starts_with($url, '//');
    }

    public function link(string $provider)
    {
        session(['social_link' => true]);
        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect('/settings/profile')->with('error', 'Authentication failed.');
        }

        $isLinking = session()->pull('social_link', false);

        if ($isLinking && Auth::check()) {
            $existingUser = User::where($provider . '_id', $socialUser->getId())->first();
            
            if ($existingUser && $existingUser->id !== Auth::id()) {
                return redirect('/settings/profile')->with('error', 'This ' . ucfirst($provider) . ' account is already linked to another user.');
            }

            Auth::user()->update([
                $provider . '_id' => $socialUser->getId(),
                'avatar' => Auth::user()->avatar ?: $socialUser->getAvatar(),
            ]);

            return redirect('/settings/profile')->with('status', ucfirst($provider) . ' account linked successfully.');
        }

        $user = User::where($provider . '_id', $socialUser->getId())
            ->orWhere('email', $socialUser->getEmail())
            ->first();

        if ($user) {
            if (!$user->{$provider . '_id'}) {
                $user->update([
                    $provider . '_id' => $socialUser->getId(),
                    'avatar' => $user->avatar ?: $socialUser->getAvatar(),
                ]);
            }
        } else {
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                'email' => $socialUser->getEmail(),
                'password' => bcrypt(Str::random(24)),
                $provider . '_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
                'email_verified_at' => now(),
            ]);
        }

        Auth::login($user);

        $redirect = session()->pull('auth_redirect');
        if ($redirect && $this->isSafeRedirect($redirect)) {
            return redirect()->to($redirect);
        }

        return redirect()->intended('/dashboard');
    }

    public function unlink(string $provider)
    {
        Auth::user()->update([
            $provider . '_id' => null,
        ]);

        return redirect('/settings/profile')->with('status', ucfirst($provider) . ' account unlinked.');
    }
}
