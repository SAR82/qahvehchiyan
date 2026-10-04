<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function index()
    {
        return ProductCategory::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $category = ProductCategory::create($data);

        return response()->json($category, 201);
    }

    public function show(ProductCategory $productCategory)
    {
        return $productCategory;
    }

    public function update(Request $request, ProductCategory $productCategory)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $productCategory->update($data);

        return $productCategory;
    }

    public function destroy(ProductCategory $productCategory)
    {
        $productCount = $productCategory->products()->count();

        if ($productCount > 0) {
            return response()->json([
                'message' => "این دسته‌بندی شامل {$productCount} محصول است. ابتدا محصولات را جابه‌جا یا حذف کنید.",
            ], 422);
        }

        $productCategory->delete();

        return response()->json(null, 204);
    }
}