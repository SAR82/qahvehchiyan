<?php

namespace App\Http\Controllers\Api;


use App\Models\Cafe;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Services\OtpService;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
    
        $user = User::where('phone', $credentials['phone'])->first();
    
        if ($user && $user->role === 'owner') {
            throw ValidationException::withMessages([
                'phone' => ['مالکان کافه باید با کد پیامکی وارد شوند.'],
            ]);
        }
    
        if (! $user || ! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'phone' => ['شماره تلفن یا رمز عبور اشتباه است.'],
            ]);
        }
    
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'phone' => ['حساب کاربری غیرفعال است.'],
            ]);
        }
    
        $token = $user->createToken('auth_token')->plainTextToken;
    
        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'خارج شدید.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('cafe'));
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'cafe_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $result = DB::transaction(function () use ($data) {
            $cafe = Cafe::create([
                'name' => $data['cafe_name'],
                'status' => 'pending',
            ]);

            $owner = User::create([
                'cafe_id' => $cafe->id,
                'phone' => $data['phone'],
                'password' => bcrypt($data['password']),
                'role' => 'owner',
            ]);

            $cafe->owner_id = $owner->id;
            $cafe->save();

            return $owner;
        });

        

        $token = $result->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'ثبت‌نام با موفقیت انجام شد. کافه شما در انتظار تایید مدیریت است.',
            'user' => $result,
            'token' => $token,
        ], 201);
    }

    public function requestOtp(Request $request, OtpService $otpService)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $owner = User::where('phone', $data['phone'])->where('role', 'owner')->first();

        if (! $owner) {
            return response()->json(['message' => 'شماره‌ای با نقش مالک کافه یافت نشد.'], 404);
        }

        $sent = $otpService->requestCode($data['phone'], 'cafe_owner');

        if (! $sent) {
            return response()->json(['message' => 'لطفاً کمی صبر کنید و دوباره تلاش کنید.'], 429);
        }

        return response()->json(['message' => 'کد تایید ارسال شد.']);
    }

    public function verifyOtp(Request $request, OtpService $otpService)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $owner = User::where('phone', $data['phone'])->where('role', 'owner')->first();

        if (! $owner) {
            return response()->json(['message' => 'شماره‌ای با نقش مالک کافه یافت نشد.'], 404);
        }

        if ($otpService->isLocked($data['phone'], 'cafe_owner')) {
            return response()->json(['message' => 'به دلیل تلاش‌های ناموفق زیاد، تا ۳۰ دقیقه دیگر امکان ورود وجود ندارد.'], 429);
        }
    
        if (! $otpService->verifyCode($data['phone'], $data['code'], 'cafe_owner')) {
            return response()->json(['message' => 'کد وارد شده نامعتبر یا منقضی شده است.'], 422);
        }

        $token = $owner->createToken('otp_login')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $owner,
        ]);
    }
}