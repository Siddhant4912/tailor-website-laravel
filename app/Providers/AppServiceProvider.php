<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                $dbSettings = \Illuminate\Support\Facades\Cache::remember('site_settings', 60 * 24, function () {
                    return \App\Models\Setting::pluck('value', 'key')->toArray();
                });

                if (isset($dbSettings['footer_address']) && !empty($dbSettings['footer_address'])) {
                    config(['company.address' => $dbSettings['footer_address']]);
                }
                if (isset($dbSettings['footer_phone']) && !empty($dbSettings['footer_phone'])) {
                    config(['company.phone' => $dbSettings['footer_phone']]);
                }
                if (isset($dbSettings['footer_email']) && !empty($dbSettings['footer_email'])) {
                    config(['company.email' => $dbSettings['footer_email']]);
                }
            }
        } catch (\Exception $e) {
            // Safe fallback during setup/migrations
        }
    }
}
