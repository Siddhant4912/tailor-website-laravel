<?php

namespace App\Services;

use App\Models\GstSetting;
use Illuminate\Support\Facades\Cache;

class GstService
{
    public function getActiveRate(): float
    {
        return Cache::remember('active_gst_rate', 3600, function () {
            $setting = GstSetting::where('is_active', true)->first();
            return $setting ? (float) $setting->rate : 0.0;
        });
    }

    public function isActiveInclusive(): bool
    {
        return Cache::remember('active_gst_inclusive', 3600, function () {
            $setting = GstSetting::where('is_active', true)->first();
            return $setting ? (bool) $setting->is_inclusive : false;
        });
    }
}
