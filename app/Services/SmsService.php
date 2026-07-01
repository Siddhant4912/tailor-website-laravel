<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $config;

    public function __construct()
    {
        $this->config = config('services.gooadvert');
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
        // 1. Sanitize and format the phone number
        $formattedPhone = $this->formatPhoneNumber($phone);
        if (!$formattedPhone) {
            Log::error("SMS send failed: Invalid phone number '{$phone}'");
            return false;
        }

        // 2. Prepare text message by matching DLT approved template exactly
        $templateText = $this->config['template_text'] ?? 'Dear Customer your one time password(OTP) for login is ##var##. Please do not share this with anyone. Shree Appaji enterprises';
        $messageText = str_replace(['##var##', '{#var#}', '{$otp}'], $otp, $templateText);

        // 3. Build API parameters
        $params = [
            'senderid' => $this->config['sender_id'],
            'channel' => $this->config['channel'],
            'DCS' => '0',
            'flashsms' => '0',
            'number' => $formattedPhone,
            'text' => $messageText,
            'route' => $this->config['route'],
            'PEId' => $this->config['peid'],
            'DLTTemplateId' => $this->config['template_id'],
        ];

        if (!empty($this->config['user']) && !empty($this->config['password'])) {
            $params['user'] = $this->config['user'];
            $params['password'] = $this->config['password'];
        } elseif (!empty($this->config['api_key'])) {
            $params['APIKey'] = $this->config['api_key'];
        }

        // 4. Send request
        try {
            Log::info('GOOADVERT PARAMS', [
                'APIKey' => !empty($this->config['api_key']) ? (substr($this->config['api_key'], 0, 5) . '***') : null,
                'user' => !empty($this->config['user']) ? $this->config['user'] : null,
                'senderid' => $this->config['sender_id'],
                'channel' => $this->config['channel'],
                'route' => $this->config['route'],
                'PEId' => $this->config['peid'],
                'DLTTemplateId' => $this->config['template_id'],
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
                // Return true only if ErrorCode is 000 (Done)
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
     * @param string $otp
     * @return bool
     */
    public function sendDeliveryOtp($phone, $otp)
    {
        // 1. Sanitize and format the phone number
        $formattedPhone = $this->formatPhoneNumber($phone);
        if (!$formattedPhone) {
            Log::error("Delivery SMS send failed: Invalid phone number '{$phone}'");
            return false;
        }

        // 2. Determine template ID and text.
        // Falls back to the approved login template if no delivery template is configured.
        $templateId = env('GOOADVERT_DELIVERY_TEMPLATE_ID', $this->config['template_id']);
        
        if ($templateId === $this->config['template_id']) {
            $messageText = "Dear Customer your one time password(OTP) for login is {$otp}. Please do not share this with anyone. Shree Appaji enterprises";
        } else {
            $deliveryTemplate = env('GOOADVERT_DELIVERY_TEMPLATE_TEXT', "Dear Customer, your delivery verification OTP for order is {$otp}. Shree Appaji enterprises");
            $messageText = str_replace(['##var##', '{#var#}', '{$otp}'], $otp, $deliveryTemplate);
        }

        // 3. Build API parameters
        $params = [
            'senderid' => $this->config['sender_id'],
            'channel' => $this->config['channel'],
            'DCS' => '0',
            'flashsms' => '0',
            'number' => $formattedPhone,
            'text' => $messageText,
            'route' => $this->config['route'],
            'PEId' => $this->config['peid'],
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
            Log::info('GOOADVERT DELIVERY PARAMS', [
                'APIKey' => !empty($this->config['api_key']) ? (substr($this->config['api_key'], 0, 5) . '***') : null,
                'user' => !empty($this->config['user']) ? $this->config['user'] : null,
                'senderid' => $this->config['sender_id'],
                'channel' => $this->config['channel'],
                'route' => $this->config['route'],
                'PEId' => $this->config['peid'],
                'DLTTemplateId' => $templateId,
                'number' => $formattedPhone,
                'text' => $messageText,
            ]);

            $url = 'http://sms.gooadvert.com/api/mt/SendSMS';
            $response = Http::get($url, $params);

            Log::info('GOOADVERT DELIVERY RESPONSE', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                return str_contains($response->body(), '"ErrorCode":"000"');
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Error calling Goo Advert SMS API for delivery: " . $e->getMessage());
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
}
