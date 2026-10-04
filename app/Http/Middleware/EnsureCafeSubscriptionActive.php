<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCafeSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->cafe_id) {
            return $next($request);
        }

        $cafe = $user->cafe;

        if (! $cafe || $cafe->status !== 'approved') {
            return response()->json([
                'message' => 'کافه شما تایید نشده یا معلق است.',
            ], 403);
        }

        $activeSubscription = $cafe
            ->subscriptions()
            ->where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->exists();

        if (! $activeSubscription) {
            return response()->json([
                'message' => 'اشتراک کافه منقضی شده یا فعال نیست.',
            ], 403);
        }

        return $next($request);
    }
}