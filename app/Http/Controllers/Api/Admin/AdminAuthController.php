<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Services\OtpService;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        return response()->json([
            'message' => 'ورود ادمین فقط با کد پیامکی امکان‌پذیر است.',
        ], 410);
    }

    public function logout(Request $request)
    {
        $request->user('admin')->currentAccessToken()->delete();

        return response()->json(['message' => 'خارج شدید.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user('admin'));
    }

    public function requestOtp(Request $request, OtpService $otpService)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $admin = AdminUser::where('phone', $data['phone'])
            ->where('is_active', true)
            ->first();

        if ($admin && ! $otpService->isLocked($data['phone'], 'admin')) {
            $otpService->requestCode($data['phone'], 'admin');
        }

        return response()->json(['message' => 'اگر این شماره ثبت شده باشد، کد ارسال شد.']);
    }

    public function verifyOtp(Request $request, OtpService $otpService)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        if ($otpService->isLocked($data['phone'], 'admin')) {
            return response()->json(['message' => 'تلاش‌های ناموفق زیاد است. بعداً دوباره امتحان کنید.'], 429);
        }

        if (! $otpService->verifyCode($data['phone'], $data['code'], 'admin')) {
            return response()->json(['message' => 'کد وارد شده نامعتبر یا منقضی شده است.'], 422);
        }

        $admin = AdminUser::where('phone', $data['phone'])
            ->where('is_active', true)
            ->first();

        if (! $admin) {
            return response()->json(['message' => 'کد وارد شده نامعتبر یا منقضی شده است.'], 422);
        }

        $token = $admin->createToken('otp_login')->plainTextToken;
        // $token = $admin->createToken('otp_login', ['*'], now()->addHours(8))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $admin,
        ]);
    }
}