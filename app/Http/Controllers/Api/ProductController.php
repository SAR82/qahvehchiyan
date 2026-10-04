<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return $query->with('category')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['nullable', Rule::exists('product_categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'product_type' => ['required', Rule::in(['drink', 'food', 'packaged', 'other'])],
            'is_active' => ['sometimes', 'boolean'],
            'is_tax_exempt' => ['sometimes', 'boolean'],
            'is_discountable' => ['sometimes', 'boolean'],
        ]);
    
        if (! empty($data['category_id'])) {
            $this->assertCategoryBelongsToCafe($data['category_id']);
        }
    
        $product = Product::create($data);
    
        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return $product->load('category');
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'category_id' => ['nullable', Rule::exists('product_categories', 'id')],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'product_type' => ['sometimes', 'required', Rule::in(['drink', 'food', 'packaged', 'other'])],
            'is_active' => ['sometimes', 'boolean'],
            'is_discountable' => ['sometimes', 'boolean'],
        ]);

        if (! empty($data['category_id'])) {
            $this->assertCategoryBelongsToCafe($data['category_id']);
        }

        $product->update($data);

        return $product;
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(null, 204);
    }  

    protected function assertCategoryBelongsToCafe(int $categoryId): void
    {
        // چون ProductCategory هم از BelongsToCafe استفاده می‌کنه،
        // اگه دسته‌بندی متعلق به کافه‌ی دیگه‌ای باشه، اصلاً پیدا نمی‌شه (404)
        ProductCategory::findOrFail($categoryId);
    }
}