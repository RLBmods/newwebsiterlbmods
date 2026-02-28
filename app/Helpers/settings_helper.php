<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

if (! function_exists('setting')) {
    /**
     * Get or set a site setting.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    function setting($key, $default = null)
    {
        return Cache::rememberForever("setting.{$key}", function () use ($key, $default) {
            $setting = Setting::where('key', $key)->first();

            if (! $setting) {
                return $default;
            }

            $value = $setting->value;

            // Handle basic type casting
            switch ($setting->type) {
                case 'boolean':
                case 'bool':
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                case 'integer':
                case 'int':
                    return (int) $value;
                case 'json':
                case 'array':
                    return json_decode($value, true);
                default:
                    return $value;
            }
        });
    }
}
