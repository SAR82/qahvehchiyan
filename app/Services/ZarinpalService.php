<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ZarinpalService
{
    protected string $merchantId;
    protected bool $sandbox;
    protected string $baseUrl;

    public function __construct()
    {
        $this->merchantId = config('services.zarinpal.merchant_id');
        $this->sandbox = config('services.zarinpal.sandbox');
        $this->baseUrl = $this->sandbox
            ? 'https://sandbox.zarinpal.com/pg/v4/payment/'
            : 'https://payment.zarinpal.com/pg/v4/payment/';
    }

    /**
     * ارسال درخواست پرداخت به زرین‌پال و گرفتن authority + لینک پرداخت
     */
    public function request(int $amountToman, string $description, string $callbackUrl, ?string $mobile = null): array
    {
        $response = Http::post($this->baseUrl . 'request.json', [
            'merchant_id' => $this->merchantId,
            'amount' => $amountToman,
            'currency' => 'IRT',
            'description' => $description,
            'callback_url' => $callbackUrl,
            'metadata' => array_filter([
                'mobile' => $mobile,
            ]),
        ]);

        $data = $response->json('data');

        if (! $data || ($data['code'] ?? null) !== 100) {
            return [
                'success' => false,
                'message' => $response->json('errors.message') ?? 'خطا در ارتباط با درگاه پرداخت.',
            ];
        }

        $payUrl = $this->sandbox
            ? "https://sandbox.zarinpal.com/pg/StartPay/{$data['authority']}"
            : "https://payment.zarinpal.com/pg/StartPay/{$data['authority']}";

        return [
            'success' => true,
            'authority' => $data['authority'],
            'pay_url' => $payUrl,
        ];
    }

    /**
     * تایید پرداخت بعد از بازگشت کاربر از زرین‌پال
     */
    public function verify(int $amountToman, string $authority): array
    {
        $response = Http::post($this->baseUrl . 'verify.json', [
            'merchant_id' => $this->merchantId,
            'amount' => $amountToman,
            'authority' => $authority,
        ]);

        $data = $response->json('data');

        // کد 100 = تایید موفق، 101 = این تراکنش قبلاً verify شده (هم موفقه)
        if ($data && in_array($data['code'] ?? null, [100, 101])) {
            return [
                'success' => true,
                'ref_id' => $data['ref_id'] ?? null,
            ];
        }

        return [
            'success' => false,
            'message' => $response->json('errors.message') ?? 'پرداخت تایید نشد.',
        ];
    }
}