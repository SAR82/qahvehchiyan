<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CafeSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
use App\Services\ZarinpalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\FinancialLedgerService;

class SubscriptionPaymentController extends Controller
{
    public function __construct(protected ZarinpalService $zarinpal)
    {
    }

    /**
     * شروع خرید اشتراک: یک CafeSubscription (pending_payment) و
     * یک SubscriptionTransaction (pending) می‌سازد و کاربر را به درگاه می‌فرستد
     */
    public function initiate(Request $request)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
        ]);

        $plan = SubscriptionPlan::findOrFail($data['plan_id']);
        $user = $request->user();
        $cafe = $user->cafe;

        // اگر اشتراک فعال و معتبر داریم، همان را نگه می‌داریم تا روزهای جدید رویش اضافه شود
        $cafeSubscription = $cafe->subscriptions()
            ->where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->orderByDesc('end_date')
            ->first();

        if (! $cafeSubscription) {
            $cafeSubscription = CafeSubscription::create([
                'cafe_id' => $cafe->id,
                'plan_id' => $plan->id,
                'status' => 'pending_payment',
            ]);
        }

        $transaction = SubscriptionTransaction::create([
            'cafe_subscription_id' => $cafeSubscription->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'status' => 'pending',
        ]);

        $callbackUrl = url("/api/subscription-payments/{$transaction->id}/verify");

        $result = $this->zarinpal->request(
            amountToman: (int) $plan->price,
            description: "خرید/تمدید اشتراک: {$plan->name}",
            callbackUrl: $callbackUrl,
            mobile: $user->phone,
        );

        if (! $result['success']) {
            $transaction->update(['status' => 'failed']);

            return response()->json(['message' => $result['message']], 422);
        }

        $transaction->update(['gateway_ref' => $result['authority']]);

        return response()->json([
            'transaction_id' => $transaction->id,
            'pay_url' => $result['pay_url'],
        ]);
    }
    /**
     * زرین‌پال بعد از پرداخت، کاربر را با Status و Authority به این آدرس برمی‌گرداند
     */
    public function verify(Request $request, SubscriptionTransaction $subscriptionTransaction)
    {
        $frontendUrl = config('app.frontend_url');
        $authority = (string) $request->query('Authority', '');
    
        $redirectTo = DB::transaction(function () use ($subscriptionTransaction, $request, $authority, $frontendUrl) {
            // قفل تا پایان تراکنش نگه داشته می‌شود، پس دو callback هم‌زمان همدیگر را رد می‌کنند
            $tx = SubscriptionTransaction::with(['cafeSubscription', 'plan'])
                ->lockForUpdate()
                ->findOrFail($subscriptionTransaction->id);
    
            // ۱) Authority باید همان باشد که موقع initiate گرفتیم
            if (! $tx->gateway_ref || ! hash_equals($tx->gateway_ref, $authority)) {
                return $frontendUrl . '/payment-result.html?status=failed';
            }
    
            // ۲) فقط یک بار پردازش شود
            if ($tx->status !== 'pending') {
                return $frontendUrl . '/payment-result.html?status=already_processed';
            }
    
            // ۳) انصراف کاربر در درگاه
            if ($request->query('Status') !== 'OK') {
                $tx->update(['status' => 'failed']);
                return $frontendUrl . '/payment-result.html?status=cancelled';
            }
    
            // ۴) تأیید از خود زرین‌پال
            $result = $this->zarinpal->verify(
                amountToman: (int) $tx->amount,
                authority: $authority,
            );
    
            if (! $result['success']) {
                $tx->update(['status' => 'failed']);
                return $frontendUrl . '/payment-result.html?status=failed';
            }
    
            // ۵) ثبت موفقیت، دفتر مالی و فعال‌سازی اشتراک، همه در همین تراکنش
            $tx->update(['status' => 'success', 'paid_at' => now()]);
    
            app(FinancialLedgerService::class)->recordSubscriptionRevenue($tx);
    
            $cafeSubscription = $tx->cafeSubscription;
            $plan = $tx->plan;
    
            $wasActive = $cafeSubscription->status === 'active'
                && $cafeSubscription->end_date
                && $cafeSubscription->end_date->isFuture();
    
            $newEndDate = $wasActive
                ? $cafeSubscription->end_date->copy()->addDays($plan->duration_days)
                : now()->addDays($plan->duration_days);
    
            $cafeSubscription->update([
                'status' => 'active',
                'start_date' => $cafeSubscription->start_date ?? now(),
                'end_date' => $newEndDate,
                'plan_id' => $plan->id,
            ]);
    
            return $frontendUrl . '/payment-result.html?status=success&ref_id=' . ($result['ref_id'] ?? '');
        });
    
        return redirect($redirectTo);
    }

    public function plans()
    {
        return \App\Models\SubscriptionPlan::where('is_active', true)->get();
    }

    public function status(Request $request)
    {
        $cafe = $request->user()->cafe;

        $activeSubscription = $cafe->subscriptions()
            ->where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->with('plan')
            ->orderByDesc('end_date')
            ->first();

        if (! $activeSubscription) {
            return response()->json([
                'has_active_subscription' => false,
                'cafe_status' => $cafe->status,
            ]);
        }

        $daysRemaining = now()->startOfDay()->diffInDays($activeSubscription->end_date, false);

        return response()->json([
            'has_active_subscription' => true,
            'cafe_status' => $cafe->status,
            'plan_name' => $activeSubscription->plan->name,
            'end_date' => $activeSubscription->end_date->toDateString(),
            'days_remaining' => (int) $daysRemaining,
        ]);
    }
}