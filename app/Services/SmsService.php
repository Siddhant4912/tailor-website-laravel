<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $config;
    protected $enabled;

    public function __construct()
    {
        $this->config = config('services.gooadvert');
        $this->enabled = (bool) config('services.gooadvert.enabled', env('SMS_ENABLED', true));
    }

    /**
     * Send OTP SMS to the given phone number.
     *
     * @param string $phone
     * @param string $otp
     * @return bool
     */
    public function sendOtp($phone, $otp)
    {
        if (!$this->enabled) {
            Log::info("SMS sending is disabled (SMS_ENABLED=false). Mocking OTP to {$phone}: {$otp}");
            return true;
        }

        // 1. Sanitize and format the phone number
        $formattedPhone = $this->formatPhoneNumber($phone);
        if (!$formattedPhone) {
            Log::error("SMS send failed: Invalid phone number '{$phone}'");
            return false;
        }

        // 2. Prepare text message by matching DLT approved template exactly
        $templateId = $this->config['template_id'] ?? '';
        $templateText = $this->config['template_text'] ?? '';
        $messageText = str_replace('##alp##', $otp, $templateText);

        // 3. Build API parameters
        $params = [
            'senderid' => $this->config['sender_id'] ?? '',
            'channel' => $this->config['channel'] ?? 'Trans',
            'DCS' => '0',
            'flashsms' => '0',
            'number' => $formattedPhone,
            'text' => $messageText,
            'route' => $this->config['route'] ?? '5',
            'PEId' => $this->config['peid'] ?? '',
            'DLTTemplateId' => $templateId,
        ];

        if (!empty($this->config['user']) && !empty($this->config['password'])) {
            $params['user'] = $this->config['user'];
            $params['password'] = $this->config['password'];
        } elseif (!empty($this->config['api_key'])) {
            $params['APIKey'] = $this->config['api_key'];
        }

        // 4. Send request
        try {
            Log::info('GOOADVERT OTP PARAMS', [
                'senderid' => $params['senderid'],
                'DLTTemplateId' => $templateId,
                'number' => $formattedPhone,
                'text' => $messageText,
            ]);

            $url = 'http://sms.gooadvert.com/api/mt/SendSMS';
            $response = Http::get($url, $params);

            Log::info('GOOADVERT RESPONSE', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                return str_contains($response->body(), '"ErrorCode":"000"');
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Error calling Goo Advert SMS API: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Delivery OTP SMS to the given phone number.
     *
     * @param string $phone
     * @param string $orderNumber
     * @param string $otp
     * @return bool
     */
    public function sendDeliveryOtp($phone, $orderNumber, $otp)
    {
        if (!$this->enabled) {
            Log::info("SMS sending is disabled (SMS_ENABLED=false). Mocking Delivery OTP to {$phone} for order {$orderNumber}: {$otp}");
            return true;
        }

        // 1. Sanitize and format the phone number
        $formattedPhone = $this->formatPhoneNumber($phone);
        if (!$formattedPhone) {
            Log::error("Delivery SMS send failed: Invalid phone number '{$phone}'");
            return false;
        }

        // 2. Determine template ID and text.
        $templateId = $this->config['delivery_template_id'] ?? '';
        $templateText = $this->config['delivery_template_text'] ?? '';
        
        $messageText = str_replace(['##alp##', '##num##'], [$orderNumber, $otp], $templateText);

        // 3. Build API parameters
        $params = [
            'senderid' => $this->config['sender_id'] ?? '',
            'channel' => $this->config['channel'] ?? 'Trans',
            'DCS' => '0',
            'flashsms' => '0',
            'number' => $formattedPhone,
            'text' => $messageText,
            'route' => $this->config['route'] ?? '5',
            'PEId' => $this->config['peid'] ?? '',
            'DLTTemplateId' => $templateId,
        ];

        if (!empty($this->config['user']) && !empty($this->config['password'])) {
            $params['user'] = $this->config['user'];
            $params['password'] = $this->config['password'];
        } elseif (!empty($this->config['api_key'])) {
            $params['APIKey'] = $this->config['api_key'];
        }

        // 4. Send request
        try {
            Log::info('GOOADVERT DELIVERY OTP PARAMS', [
                'senderid' => $params['senderid'],
                'DLTTemplateId' => $templateId,
                'number' => $formattedPhone,
                'text' => $messageText,
            ]);

            $url = 'http://sms.gooadvert.com/api/mt/SendSMS';
            $response = Http::get($url, $params);

            Log::info('GOOADVERT DELIVERY OTP RESPONSE', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                return str_contains($response->body(), '"ErrorCode":"000"');
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Error calling Goo Advert SMS API for delivery OTP: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Out for Delivery SMS to the given phone number.
     *
     * @param string $phone
     * @param string $orderNumber
     * @return bool
     */
    public function sendOutForDelivery($phone, $orderNumber)
    {
        if (!$this->enabled) {
            Log::info("SMS sending is disabled (SMS_ENABLED=false). Mocking Out for Delivery SMS to {$phone} for order {$orderNumber}");
            return true;
        }

        // 1. Sanitize and format the phone number
        $formattedPhone = $this->formatPhoneNumber($phone);
        if (!$formattedPhone) {
            Log::error("Out for Delivery SMS send failed: Invalid phone number '{$phone}'");
            return false;
        }

        // 2. Determine template ID and text.
        $templateId = $this->config['out_for_delivery_template_id'] ?? '';
        $templateText = $this->config['out_for_delivery_template_text'] ?? '';
        $messageText = str_replace('##alp##', $orderNumber, $templateText);

        // 3. Build API parameters
        $params = [
            'senderid' => $this->config['sender_id'] ?? '',
            'channel' => $this->config['channel'] ?? 'Trans',
            'DCS' => '0',
            'flashsms' => '0',
            'number' => $formattedPhone,
            'text' => $messageText,
            'route' => $this->config['route'] ?? '5',
            'PEId' => $this->config['peid'] ?? '',
            'DLTTemplateId' => $templateId,
        ];

        if (!empty($this->config['user']) && !empty($this->config['password'])) {
            $params['user'] = $this->config['user'];
            $params['password'] = $this->config['password'];
        } elseif (!empty($this->config['api_key'])) {
            $params['APIKey'] = $this->config['api_key'];
        }

        // 4. Send request
        try {
            Log::info('GOOADVERT OUT FOR DELIVERY PARAMS', [
                'senderid' => $params['senderid'],
                'DLTTemplateId' => $templateId,
                'number' => $formattedPhone,
                'text' => $messageText,
            ]);

            $url = 'http://sms.gooadvert.com/api/mt/SendSMS';
            $response = Http::get($url, $params);

            Log::info('GOOADVERT OUT FOR DELIVERY RESPONSE', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                return str_contains($response->body(), '"ErrorCode":"000"');
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Error calling Goo Advert SMS API for out for delivery: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Format number to comply with Indian carrier requirements (91 prefix).
     *
     * @param string $phone
     * @return string|null
     */
    protected function formatPhoneNumber($phone)
    {
        // Strip non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Standard Indian mobile number length is 10 digits
        if (strlen($cleaned) === 10) {
            return '91' . $cleaned;
        }

        // If it already has 91 followed by 10 digits, or similar format
        if (strlen($cleaned) === 12 && str_starts_with($cleaned, '91')) {
            return $cleaned;
        }

        // Return cleaned digits if it doesn't match standard length but seems okay
        if (strlen($cleaned) >= 10 && strlen($cleaned) <= 15) {
            return $cleaned;
        }

        return null;
    }

    /**
     * Send 15-Minute Admin Activity Summary SMS.
     * Disabled by default (pending DLT template approval from operator).
     *
     * @param string $phone
     * @param int $orderCount
     * @param int $appointmentCount
     * @param float $totalAmount
     * @return bool
     */
    public function sendAdminActivitySummary(string $phone, int $orderCount, int $appointmentCount, float $totalAmount = 0.0): bool
    {
        $adminSmsEnabled = (bool) env('ADMIN_SMS_SUMMARY_ENABLED', false);

        if (!$adminSmsEnabled) {
            Log::info("Admin activity summary SMS is currently DISABLED pending DLT approval (ADMIN_SMS_SUMMARY_ENABLED=false). Mock summary for Admin ({$phone}): {$orderCount} new orders (Rs. {$totalAmount}), {$appointmentCount} new appointments.");
            return true;
        }

        $formattedPhone = $this->formatPhoneNumber($phone);
        if (!$formattedPhone) {
            Log::error("Admin SMS send failed: Invalid phone number '{$phone}'");
            return false;
        }

        $templateId = env('GOOADVERT_ADMIN_SUMMARY_TEMPLATE_ID', '');
        $templateText = env('GOOADVERT_ADMIN_SUMMARY_TEMPLATE_TEXT', 'Dear Admin, SwiDhaagha received ##alp## new orders and ##num## new appointments in the last 15 minutes.');
        $messageText = str_replace(['##alp##', '##num##'], [(string)$orderCount, (string)$appointmentCount], $templateText);

        $params = [
            'senderid' => $this->config['sender_id'] ?? '',
            'channel' => $this->config['channel'] ?? 'Trans',
            'DCS' => '0',
            'flashsms' => '0',
            'number' => $formattedPhone,
            'text' => $messageText,
            'route' => $this->config['route'] ?? '5',
            'PEId' => $this->config['peid'] ?? '',
            'DLTTemplateId' => $templateId,
        ];

        if (!empty($this->config['user']) && !empty($this->config['password'])) {
            $params['user'] = $this->config['user'];
            $params['password'] = $this->config['password'];
        } elseif (!empty($this->config['api_key'])) {
            $params['APIKey'] = $this->config['api_key'];
        }

        try {
            $url = 'http://sms.gooadvert.com/api/mt/SendSMS';
            $response = Http::get($url, $params);
            return $response->successful() && str_contains($response->body(), '"ErrorCode":"000"');
        } catch (\Exception $e) {
            Log::error("Error calling Goo Advert SMS API for admin activity summary: " . $e->getMessage());
            return false;
        }
    }
}
