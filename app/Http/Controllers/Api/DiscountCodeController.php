<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use Illuminate\Http\Request;

class DiscountCodeController extends Controller
{
    public function index()
    {
        return DiscountCode::latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        return response()->json(DiscountCode::create($data), 201);
    }

    public function update(Request $request, DiscountCode $discountCode)
    {
        $data = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $discountCode->update($data);

        return $discountCode;
    }
}