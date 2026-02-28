<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use App\Services\SecurityLogger;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        // Check if there's a redirect parameter in the request (e.g. from checkout)
        $redirect = $request->input('redirect');
        if ($redirect && $this->isSafeRedirect($redirect)) {
            return redirect()->to($redirect);
        }

        $role = auth()->user()->role;

        if ($role === 'admin') {
            return redirect()->intended('/admin/dashboard');
        } elseif ($role === 'reseller') {
            return redirect()->intended('/reseller/dashboard');
        }

        return redirect()->intended('/dashboard');
    }

    /**
     * Only allow relative paths to prevent open redirects.
     */
    private function isSafeRedirect(string $url): bool
    {
        $url = trim($url);
        return $url !== '' && $url[0] === '/' && ! str_starts_with($url, '//');
    }
}
