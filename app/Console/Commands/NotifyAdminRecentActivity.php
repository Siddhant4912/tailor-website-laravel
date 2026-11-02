<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Setting;
use App\Mail\AdminRecentActivityMail;
use App\Services\SmsService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotifyAdminRecentActivity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:notify-recent-activity {--minutes=60 : Check interval in minutes (default 60 mins / 1 hour)} {--force : Send notification even if counts are 0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks for new orders & appointments in the last 1 hour (or specified minutes) and alerts admin via Email & SMS (if approved)';

    /**
     * Execute the console command.
     */
    public function handle(SmsService $smsService)
    {
        $minutes = (int) ($this->option('minutes') ?: 60);
        $since = now()->subMinutes($minutes);
        $windowText = ($minutes === 60) ? '1 hour' : "{$minutes} minutes";

        $this->info("Checking for orders and appointments received since {$since->format('Y-m-d H:i:s')} (last {$windowText})...");

        // 1. Fetch newly received Orders
        $orders = Order::with(['customer', 'items'])
            ->where('created_at', '>=', $since)
            ->where('status', '!=', 'cancelled')
            ->latest()
            ->get();

        // 2. Fetch newly received Appointments
        $appointments = Appointment::with(['customer', 'items'])
            ->where('created_at', '>=', $since)
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'draft')
            ->latest()
            ->get();

        $orderCount = $orders->count();
        $apptCount = $appointments->count();

        $this->info("Found: {$orderCount} new orders, {$apptCount} new appointments.");

        // Rule: If 0 received and not forced, DO NOT send message
        if ($orderCount === 0 && $apptCount === 0 && !$this->option('force')) {
            $msg = "No new orders or appointments in the last {$windowText}. Notification skipped (count is 0).";
            $this->comment($msg);
            Log::info("[AdminNotifyActivity] {$msg}");
            return 0;
        }

        // 3. Determine Admin Email
        $adminEmail = env('ADMIN_NOTIFICATION_EMAIL');
        if (empty($adminEmail)) {
            $adminUser = User::where('role', 'admin')->whereNotNull('email')->first();
            $adminEmail = $adminUser?->email ?: 'swidhaaghaindia@gmail.com';
        }

        // 4. Determine Admin Phone
        $adminPhone = env('ADMIN_NOTIFICATION_PHONE');
        if (empty($adminPhone)) {
            $phoneSetting = Setting::where('key', 'admin_phone')->orWhere('key', 'footer_phone')->first();
            if ($phoneSetting && !empty($phoneSetting->value)) {
                $adminPhone = $phoneSetting->value;
            } else {
                $adminUser = User::where('role', 'admin')->whereNotNull('phone')->first();
                $adminPhone = $adminUser?->phone ?: '9876543210';
            }
        }

        // 5. Send Email Alert
        try {
            Mail::to($adminEmail)->send(new AdminRecentActivityMail($orders, $appointments, $minutes));
            $this->info("✅ Email alert successfully sent to: {$adminEmail}");
            Log::info("[AdminNotifyActivity] Activity digest email sent to {$adminEmail} (Orders: {$orderCount}, Appts: {$apptCount}).");
        } catch (\Exception $e) {
            $this->error("❌ Failed to send email alert: " . $e->getMessage());
            Log::error("[AdminNotifyActivity] Failed to send email to {$adminEmail}: " . $e->getMessage());
        }

        // 6. Trigger Admin SMS (Kept disabled by default until user receives DLT approval)
        try {
            $totalAmount = (float) $orders->sum('total_amount');
            $smsSent = $smsService->sendAdminActivitySummary($adminPhone, $orderCount, $apptCount, $totalAmount);
            if ($smsSent) {
                $this->info("📱 SMS summary processed for Admin Phone: {$adminPhone}");
            }
        } catch (\Exception $e) {
            $this->warn("⚠️ SMS summary dispatch error: " . $e->getMessage());
            Log::error("[AdminNotifyActivity] SMS error for {$adminPhone}: " . $e->getMessage());
        }

        $this->info("Completed {$windowText} activity check.");
        return 0;
    }
}
