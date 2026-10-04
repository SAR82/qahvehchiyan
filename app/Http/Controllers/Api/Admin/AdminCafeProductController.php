<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cafe;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class AdminCafeProductController extends Controller
{
    public function index(Cafe $cafe)
    {
        return Product::where('cafe_id', $cafe->id)->with('category')->get();
    }

    public function store(Request $request, Cafe $cafe)
    {
        $data = $request->validate([
            'category_id' => ['nullable', Rule::exists('product_categories', 'id')->where('cafe_id', $cafe->id)],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'product_type' => ['required', Rule::in(['drink', 'food', 'packaged', 'other'])],
        ]);

        $data['cafe_id'] = $cafe->id;

        $product = Product::create($data);

        return response()->json($product, 201);
    }

    public function update(Request $request, Cafe $cafe, Product $product)
    {
        if ($product->cafe_id !== $cafe->id) {
            abort(404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $product->update($data);

        return $product;
    }

    public function destroy(Cafe $cafe, Product $product)
    {
        if ($product->cafe_id !== $cafe->id) {
            abort(404);
        }

        $product->delete();

        return response()->json(null, 204);
    }
}