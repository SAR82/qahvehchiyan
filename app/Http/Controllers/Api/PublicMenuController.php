<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cafe;
use Illuminate\Http\Request;

class PublicMenuController extends Controller
{
    public function show(Cafe $cafe)
    {
        if ($cafe->status !== 'approved') {
            return response()->json([
                'message' => 'این کافه در دسترس نیست.',
            ], 404);
        }

        $products = $cafe->products()
            ->where('is_active', true)
            ->with('category')
            ->get()
            ->groupBy(fn ($product) => $product->category?->name ?? 'سایر');

        return response()->json([
            'cafe' => [
                'id' => $cafe->id,
                'name' => $cafe->name,
            ],
            'menu' => $products,
        ]);
    }
}