<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(string $receptor, string $message): bool
    {
        $apiKey = config('services.kavenegar.api_key');

        if (! $apiKey) {
            Log::warning('Kavenegar API key not configured; SMS not sent.', ['receptor' => $receptor]);
            return false;
        }

        try {
            $response = Http::get("https://api.kavenegar.com/v1/{$apiKey}/sms/send.json", [
                'receptor' => $receptor,
                'sender' => config('services.kavenegar.sender'),
                'message' => $message,
            ]);

            if (! $response->successful()) {
                Log::error('Kavenegar SMS failed', [
                    'receptor' => $receptor,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Kavenegar SMS exception', [
                'receptor' => $receptor,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}