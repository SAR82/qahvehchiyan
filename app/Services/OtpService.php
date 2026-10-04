<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Cache;

class OtpService
{
    public function __construct(protected SmsService $sms)
    {
    }

    /**
     * یک کد جدید می‌سازد و پیامک می‌کند.
     * برمی‌گرداند: true اگر ارسال شد، false اگر به دلیل محدودیت نرخ ارسال رد شد.
     */
    public function requestCode(string $phone, string $purpose): bool
    {
        $throttleKey = "otp_throttle:{$purpose}:{$phone}";

        if (Cache::has($throttleKey)) {
            return false; // باید حداقل ۶۰ ثانیه صبر کند
        }

        // کدهای قبلی مصرف‌نشده‌ی همین شماره را باطل می‌کنیم
        OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = (string) random_int(10000, 99999);

        OtpCode::create([
            'phone' => $phone,
            'code' => $code,
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(2),
        ]);

        Cache::put($throttleKey, true, 60);
        Cache::forget("otp_attempts:{$purpose}:{$phone}");

        $this->sms->send($phone, "قهوه‌چیان: کد ورود شما {$code} است. تا ۲ دقیقه معتبر است.");

        return true;
    }

    /**
     * کد را بررسی می‌کند. در صورت درست بودن true برمی‌گرداند و کد را مصرف‌شده علامت می‌زند.
     * برای جلوگیری از حدس‌زدن brute-force، بعد از ۳ تلاش ناموفق به مدت ۳۰ دقیقه قفل می‌شود.
     */
    public function verifyCode(string $phone, string $code, string $purpose): bool
    {
        $attemptsKey = "otp_attempts:{$purpose}:{$phone}";
        $attempts = Cache::get($attemptsKey, 0);

        if ($attempts >= 3) {
            return false;
        }

        $otp = OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->where('code', $code)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $otp) {
            Cache::put($attemptsKey, $attempts + 1, now()->addMinutes(30));
            return false;
        }

        $otp->consumed_at = now();
        $otp->save();

        Cache::forget($attemptsKey);

        return true;
    }

    /**
     * تعیین می‌کند آیا شماره در حال حاضر قفل است (به دلیل تلاش‌های ناموفق زیاد)
     */
    public function isLocked(string $phone, string $purpose): bool
    {
        return Cache::get("otp_attempts:{$purpose}:{$phone}", 0) >= 3;
    }
}