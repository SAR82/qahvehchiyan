<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cafe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class AdminCafeUserController extends Controller
{
    public function index(Cafe $cafe)
    {
        return User::where('cafe_id', $cafe->id)->get();
    }

    public function store(Request $request, Cafe $cafe)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in(['owner', 'manager', 'cashier', 'warehouse_keeper'])],
        ]);

        $user = User::create([
            'cafe_id' => $cafe->id,
            'phone' => $data['phone'],
            'password' => bcrypt($data['password']),
            'role' => $data['role'],
        ]);

        return response()->json($user, 201);
    }

    public function update(Request $request, Cafe $cafe, User $user)
    {
        if ($user->cafe_id !== $cafe->id) {
            abort(404);
        }

        $data = $request->validate([
            'role' => ['sometimes', 'required', Rule::in(['owner', 'manager', 'cashier', 'warehouse_keeper'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user->update($data);

        return $user;
    }

    public function destroy(Cafe $cafe, User $user)
    {
        if ($user->cafe_id !== $cafe->id) {
            abort(404);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}