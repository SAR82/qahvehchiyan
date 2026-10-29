<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private const BASE_URL = 'https://api.sms.ir/v1/send';

    /** ارسال کد OTP با قالب وریفای */
    public function sendOtp(string $mobile, string $code): bool
    {
        $templateId = config('services.smsir.otp_template_id');

        if (! $templateId) {
            Log::warning('SMS.ir OTP template id not configured.');
            return false;
        }

        return $this->post('verify', [
            'mobile' => $mobile,
            'templateId' => (int) $templateId,
            'parameters' => [
                ['name' => 'CODE', 'value' => $code],
            ],
        ], $mobile);
    }

    /** ارسال پیام متنی عادی (مثلاً هشدار موجودی) */
    public function send(string $receptor, string $message): bool
    {
        $line = config('services.smsir.line_number');

        if (! $line) {
            Log::warning('SMS.ir line number not configured.');
            return false;
        }

        return $this->post('bulk', [
            'lineNumber' => (int) $line,
            'messageText' => $message,
            'mobiles' => [$receptor],
            'sendDateTime' => null,
        ], $receptor);
    }

    private function post(string $endpoint, array $payload, string $receptor): bool
    {
        $apiKey = config('services.smsir.api_key');

        if (! $apiKey) {
            Log::warning('SMS.ir API key not configured; SMS not sent.');
            return false;
        }

        try {
            $response = Http::withHeaders(['X-API-KEY' => $apiKey])
                ->acceptJson()
                ->timeout(10)
                ->post(self::BASE_URL . '/' . $endpoint, $payload);

            if (! $response->successful() || (int) $response->json('status') !== 1) {
                Log::error('SMS.ir send failed', [
                    'receptor' => $this->mask($receptor),
                    'http' => $response->status(),
                    'status' => $response->json('status'),
                    'message' => $response->json('message'),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('SMS.ir exception', [
                'receptor' => $this->mask($receptor),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // شماره‌ی کامل در لاگ نمی‌نویسیم
    private function mask(string $mobile): string
    {
        return substr($mobile, 0, 4) . '***' . substr($mobile, -3);
    }
}