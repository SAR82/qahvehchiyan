<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return User::where('cafe_id', $request->user()->cafe_id)->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'unique:users,phone'],
            'password' => ['required', 'string', Password::min(6)],
            'role' => ['required', Rule::in(['owner', 'manager', 'cashier', 'warehouse_keeper'])],
        ]);

        $user = User::create([
            'cafe_id' => $request->user()->cafe_id,
            'phone' => $data['phone'],
            'password' => bcrypt($data['password']),
            'role' => $data['role'],
        ]);

        return response()->json($user, 201);
    }

    public function show(Request $request, User $user)
    {
        $this->authorizeSameCafe($request, $user);

        return $user;
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeSameCafe($request, $user);

        $data = $request->validate([
            'role' => ['sometimes', 'required', Rule::in(['owner', 'manager', 'cashier', 'warehouse_keeper'])],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'required', 'string', Password::min(6)],
        ]);

        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        $user->update($data);

        // توکن‌های قبلی هنگام غیرفعال‌شدن، تغییر رمز یا تغییر نقش باطل شوند
        if (isset($data['password'])
            || (isset($data['is_active']) && ! $data['is_active'])
            || isset($data['role'])) {
            $user->tokens()->delete();
        }

        return $user;
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorizeSameCafe($request, $user);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'نمی‌توانید حساب کاربری خودتان را حذف کنید.',
            ], 422);
        }

        $hasActivity = $user->orders()->exists()
            || \App\Models\InventoryTransaction::where('created_by', $user->id)->exists();

        if ($hasActivity) {
            $user->update(['is_active' => false]);

            return response()->json([
                'message' => 'این کاربر سابقه‌ی فعالیت دارد؛ حذف کامل ممکن نیست، بنابراین غیرفعال شد.',
            ]);
        }

        $user->delete();

        return response()->json(null, 204);
    }

    protected function authorizeSameCafe(Request $request, User $user): void
    {
        if ($user->cafe_id !== $request->user()->cafe_id) {
            abort(404);
        }
    }
}