<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class SettingController extends Controller
{
    /**
     * Display the site settings page.
     */
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key');
        
        return Inertia::render('Admin/Settings', [
            'initialSettings' => $settings
        ]);
    }

    /**
     * Update site settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string',
        ]);

        foreach ($validated['settings'] as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $this->inferType($key)]
            );
            
            // Clear cache for this setting
            Cache::forget("setting.{$key}");
        }

        return back()->with('success', 'Settings updated successfully!');
    }

    /**
     * Infer type based on key or value.
     */
    protected function inferType($key)
    {
        if (str_contains($key, 'mode') || str_contains($key, 'enabled')) {
            return 'boolean';
        }
        
        return 'string';
    }
}
