<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class AdminPlanController extends Controller
{
    public function index()
    {
        return SubscriptionPlan::latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $plan = SubscriptionPlan::create($data);

        return response()->json($plan, 201);
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'duration_days' => ['sometimes', 'required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $plan->update($data);

        return $plan;
    }

    public function destroy(SubscriptionPlan $plan)
    {
        if ($plan->cafeSubscriptions()->exists()) {
            return response()->json([
                'message' => 'این پلن قبلاً استفاده شده و قابل حذف نیست. می‌توانید آن را غیرفعال کنید.',
            ], 422);
        }

        $plan->delete();

        return response()->json(null, 204);
    }
}