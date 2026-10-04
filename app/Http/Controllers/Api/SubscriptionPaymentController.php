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

        $subscriptionTransaction = DB::transaction(function () use ($subscriptionTransaction) {
            return SubscriptionTransaction::lockForUpdate()->find($subscriptionTransaction->id);
        });

        if ($subscriptionTransaction->status !== 'pending') {
            return redirect($frontendUrl . '/payment-result.html?status=already_processed');
        }

        if ($request->query('Status') !== 'OK') {
            $subscriptionTransaction->update(['status' => 'failed']);
            return redirect($frontendUrl . '/payment-result.html?status=cancelled');
        }

        $result = $this->zarinpal->verify(
            amountToman: (int) $subscriptionTransaction->amount,
            authority: $request->query('Authority'),
        );

        if (! $result['success']) {
            $subscriptionTransaction->update(['status' => 'failed']);
            return redirect($frontendUrl . '/payment-result.html?status=failed');
        }

        $subscriptionTransaction->update([
            'status' => 'success',
            'paid_at' => now(),
        ]);

        app(FinancialLedgerService::class)->recordSubscriptionRevenue($subscriptionTransaction);

        $cafeSubscription = $subscriptionTransaction->cafeSubscription;
        $plan = $subscriptionTransaction->plan;

        $wasActive = $cafeSubscription->status === 'active'
            && $cafeSubscription->end_date
            && $cafeSubscription->end_date->isFuture();

        // اگر اشتراک از قبل فعال بود، روزهای جدید را روی end_date فعلی اضافه می‌کنیم
        // در غیر این صورت، از امروز شروع می‌کنیم
        $newEndDate = $wasActive
            ? $cafeSubscription->end_date->copy()->addDays($plan->duration_days)
            : now()->addDays($plan->duration_days);

        $cafeSubscription->update([
            'status' => 'active',
            'start_date' => $cafeSubscription->start_date ?? now(),
            'end_date' => $newEndDate,
            'plan_id' => $plan->id,
        ]);

        return redirect($frontendUrl . '/payment-result.html?status=success&ref_id=' . $result['ref_id']);
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