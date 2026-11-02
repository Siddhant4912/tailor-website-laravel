<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * 1-Hour Admin Activity Notification
 * Automatically checks for new orders & appointments in the last 1 hour.
 * If 0 orders/appointments are received, notification is skipped.
 * Sends email digest to admin email and triggers SMS service (disabled until approved).
 */
Schedule::command('admin:notify-recent-activity --minutes=60')->hourly();
