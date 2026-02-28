<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'workspace_permissions' => $request->user() ? [
                    'generate' => $request->user()->hasWorkspacePermission('generate'),
                    'view' => $request->user()->hasWorkspacePermission('view'),
                    'reset' => $request->user()->hasWorkspacePermission('reset'),
                    'access_api' => $request->user()->hasWorkspacePermission('access_api'),
                ] : [],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'keys' => $request->session()->get('keys'),
                'product_name' => $request->session()->get('product_name'),
            ],
            'settings' => [
                'site_name' => setting('site_name', config('app.name')),
                'site_logo' => setting('site_logo'),
                'site_favicon' => setting('site_favicon'),
                'copyright_text' => setting('copyright_text', 'Copyright © ' . date('Y') . ' ' . config('app.name')),
                'maintenance_mode' => setting('maintenance_mode', false),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
