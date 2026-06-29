<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    /**
     * Get all public settings (e.g. for footer, frontend config)
     */
    public function index()
    {
        // Cache the settings for 24 hours to reduce DB queries
        $settings = Cache::remember('site_settings', 60 * 24, function () {
            return Setting::pluck('value', 'key')->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $settings,
            'message' => 'Settings fetched successfully.'
        ]);
    }

    /**
     * Bulk update settings (Admin only)
     */
    public function update(Request $request)
    {
        $data = $request->all();

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        // Clear the cache
        Cache::forget('site_settings');

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully.',
            'data' => Setting::pluck('value', 'key')->toArray()
        ]);
    }
}
