<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class OtpService
{
    protected const MAX_ATTEMPTS = 5;
    protected const LOCK_SECONDS = 1800;

    public function __construct(protected SmsService $sms)
    {
    }

    public function requestCode(string $phone, string $purpose): bool
    {
        // Cache::add اتمیک است: فقط یک درخواست در ۶۰ ثانیه موفق می‌شود
        if (! Cache::add("otp_throttle:{$purpose}:{$phone}", true, 60)) {
            return false;
        }

        OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = (string) random_int(100000, 999999);

        OtpCode::create([
            'phone' => $phone,
            'code' => $this->hash($code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(2),
        ]);

        // عمداً شمارنده‌ی تلاش‌ها را ریست نمی‌کنیم

        $this->sms->send($phone, "قهوه‌چیان: کد ورود شما {$code} است. تا ۲ دقیقه معتبر است.");

        return true;
    }

    public function verifyCode(string $phone, string $code, string $purpose): bool
    {
        $key = $this->attemptsKey($phone, $purpose);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return false;
        }

        // اول تلاش را می‌شماریم، بعد بررسی می‌کنیم؛ پس درخواست‌های موازی هم شمرده می‌شوند
        RateLimiter::hit($key, self::LOCK_SECONDS);

        $otp = OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->where('code', $this->hash($code))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $otp) {
            return false;
        }

        // مصرف اتمیک: اگر دو درخواست هم‌زمان با کد درست بیایند، فقط یکی موفق می‌شود
        $consumed = OtpCode::whereKey($otp->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        if ($consumed !== 1) {
            return false;
        }

        RateLimiter::clear($key);

        return true;
    }

    public function isLocked(string $phone, string $purpose): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->attemptsKey($phone, $purpose),
            self::MAX_ATTEMPTS
        );
    }

    protected function attemptsKey(string $phone, string $purpose): string
    {
        return "otp_verify:{$purpose}:{$phone}";
    }

    // کد در دیتابیس به‌صورت hash ذخیره می‌شود، نه متن ساده
    protected function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}