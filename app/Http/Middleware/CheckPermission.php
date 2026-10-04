<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    protected array $rolePermissions = [
        'owner' => ['*'],
        'manager' => ['order.*', 'inventory.*', 'product.*', 'report.read', 'users.read', 'payment.create', 'finance.*', 'table.*', 'discount.*'],
        'cashier' => ['order.create', 'order.read', 'order.cancel', 'payment.create', 'product.read', 'table.read'],
        'warehouse_keeper' => ['inventory.read', 'inventory.write'],
    ];

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $this->hasPermission($user->role, $permission)) {
            return response()->json([
                'message' => 'دسترسی غیرمجاز.',
            ], 403);
        }

        return $next($request);
    }

    protected function hasPermission(string $role, string $permission): bool
    {
        $allowed = $this->rolePermissions[$role] ?? [];

        foreach ($allowed as $pattern) {
            if ($pattern === '*') {
                return true;
            }

            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                if (str_starts_with($permission, $prefix)) {
                    return true;
                }
            }

            if ($pattern === $permission) {
                return true;
            }
        }

        return false;
    }
}