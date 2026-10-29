<?php

namespace App\Http\Controllers\Api\Admin;


use App\Models\CafeSubscription;
use App\Models\SubscriptionPlan;
use App\Http\Controllers\Controller;
use App\Models\Cafe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;


class CafeAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Cafe::query()->with('owner');

        if ($request->has('status')) {
            $query->where('status', $request->string('status'));
        }

        $cafes = $query->latest()->get();

        $cafes->each(function ($cafe) {
            $activeSubscription = $cafe->subscriptions()
                ->where('status', 'active')
                ->where('end_date', '>=', now()->toDateString())
                ->orderByDesc('end_date')
                ->first();

            $cafe->days_remaining = $activeSubscription
                ? now()->startOfDay()->diffInDays($activeSubscription->end_date, false)
                : null;
        });

        return $cafes;
    }

    public function store(Request $request)
    {
        $data = $request->validate(
            [
                'cafe_name' => ['required', 'string', 'max:255'],
                'owner_phone' => ['required', 'string', 'regex:/^09\d{9}$/', 'unique:users,phone'],
            ],
            [
                'owner_phone.unique' => 'این شماره قبلاً ثبت شده است.',
                'owner_phone.regex' => 'شماره تلفن باید به‌صورت 09123456789 باشد.',
            ]
        );

        $result = DB::transaction(function () use ($data) {
            $cafe = Cafe::create(['name' => $data['cafe_name'], 'status' => 'approved']);

            $owner = User::create([
                'cafe_id' => $cafe->id,
                'phone' => $data['owner_phone'],
                'password' => bcrypt(Str::random(40)), // مالک فقط با کد پیامکی وارد می‌شود
                'role' => 'owner',
            ]);

            $cafe->owner_id = $owner->id;
            $cafe->save();

            return $cafe->load('owner');
        });

        return response()->json($result, 201);
    }


    public function show(Cafe $cafe)
    {
        return $cafe->load('owner', 'subscriptions.plan');
    }

    public function updateStatus(Request $request, Cafe $cafe)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'suspended'])],
        ]);

        $cafe->status = $data['status'];
        $cafe->save();

        return $cafe;
    }

    public function activateSubscription(Request $request, Cafe $cafe)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
        ]);

        $plan = SubscriptionPlan::findOrFail($data['plan_id']);
        $days = $data['duration_days'] ?? $plan->duration_days;

        $cafeSubscription = $cafe->subscriptions()
            ->where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->orderByDesc('end_date')
            ->first();

        if ($cafeSubscription) {
            $cafeSubscription->update([
                'end_date' => $cafeSubscription->end_date->copy()->addDays($days),
                'plan_id' => $plan->id,
            ]);
        } else {
            $cafeSubscription = CafeSubscription::create([
                'cafe_id' => $cafe->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'start_date' => now(),
                'end_date' => now()->addDays($days),
            ]);
        }

        return response()->json($cafeSubscription->fresh()->load('plan'), 201);
    }

    public function dashboard()
    {
        return response()->json([
            'cafes' => [
                'total' => Cafe::count(),
                'pending' => Cafe::where('status', 'pending')->count(),
                'approved' => Cafe::where('status', 'approved')->count(),
                'rejected' => Cafe::where('status', 'rejected')->count(),
                'suspended' => Cafe::where('status', 'suspended')->count(),
            ],
            'subscriptions' => [
                'active' => \App\Models\CafeSubscription::where('status', 'active')->count(),
                'expired' => \App\Models\CafeSubscription::where('status', 'expired')->count(),
                'pending_payment' => \App\Models\CafeSubscription::where('status', 'pending_payment')->count(),
            ],
            'revenue' => [
                'total' => \App\Models\SubscriptionTransaction::where('status', 'success')->sum('amount'),
                'this_month' => \App\Models\SubscriptionTransaction::where('status', 'success')
                    ->whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->sum('amount'),
            ],
        ]);
    }

    public function destroy(Cafe $cafe)
    {
        DB::transaction(function () use ($cafe) {
            $id = $cafe->id;

            $orderIds   = DB::table('orders')->where('cafe_id', $id)->pluck('id');
            $productIds = DB::table('products')->where('cafe_id', $id)->pluck('id');
            $itemIds    = DB::table('inventory_items')->where('cafe_id', $id)->pluck('id');
            $subIds     = DB::table('cafe_subscriptions')->where('cafe_id', $id)->pluck('id');

            DB::table('inventory_transactions')->whereIn('item_id', $itemIds)->delete();

            DB::table('payments')->whereIn('order_id', $orderIds)->delete();
            DB::table('invoices')->whereIn('order_id', $orderIds)->delete();
            DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
            DB::table('orders')->where('cafe_id', $id)->delete();

            DB::table('product_ingredients')->whereIn('product_id', $productIds)->delete();
            DB::table('products')->where('cafe_id', $id)->delete();
            DB::table('inventory_items')->where('cafe_id', $id)->delete();
            DB::table('product_categories')->where('cafe_id', $id)->delete();
            DB::table('cafe_tables')->where('cafe_id', $id)->delete();
            DB::table('discount_codes')->where('cafe_id', $id)->delete();
            DB::table('financial_transactions')->where('cafe_id', $id)->delete();
            DB::table('financial_categories')->where('cafe_id', $id)->delete();
            DB::table('cafe_order_counters')->where('cafe_id', $id)->delete();

            DB::table('subscription_transactions')->whereIn('cafe_subscription_id', $subIds)->delete();
            DB::table('cafe_subscriptions')->where('cafe_id', $id)->delete();

            DB::table('cafes')->where('id', $id)->update(['owner_id' => null]);

            $userIds = DB::table('users')->where('cafe_id', $id)->pluck('id');
            DB::table('personal_access_tokens')
                ->where('tokenable_type', \App\Models\User::class)
                ->whereIn('tokenable_id', $userIds)
                ->delete();
            DB::table('users')->where('cafe_id', $id)->delete();

            $cafe->delete();
        });

        return response()->json(null, 204);
    }


    public function impersonate(Request $request, Cafe $cafe)
    {
        if ($request->user('admin')->role !== 'super_admin') {
            return response()->json([
                'message' => 'فقط مدیر ارشد اجازه‌ی ورود به پنل کافه‌ها را دارد.',
            ], 403);
        }

        $owner = $cafe->owner ?? $cafe->users()->where('role', 'owner')->first();

        if (! $owner) {
            return response()->json(['message' => 'این کافه مالکی ندارد.'], 422);
        }

        $token = $owner->createToken(
            'impersonated_by_admin',
            ['*'],
            now()->addHours(2)
        )->plainTextToken;

        // ← کد جدید اینجاست
        \Illuminate\Support\Facades\Log::warning('admin impersonation', [
            'admin_id' => $request->user('admin')->id,
            'cafe_id' => $cafe->id,
            'owner_id' => $owner->id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'token' => $token,
            'user' => $owner,
        ]);
    }
}