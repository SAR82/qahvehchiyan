<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductIngredient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductIngredientController extends Controller
{
    public function index(Product $product)
    {
        return $product->ingredients()->with('ingredient')->get();
    }

    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'ingredient_id' => ['required', Rule::exists('inventory_items', 'id')],
            'quantity_required' => ['required', 'numeric', 'min:0.001'],
        ]);

        // اطمینان از اینکه کالای انبار متعلق به همین کافه است
        $item = InventoryItem::findOrFail($data['ingredient_id']);

        $ingredient = ProductIngredient::updateOrCreate(
            ['product_id' => $product->id, 'ingredient_id' => $item->id],
            ['quantity_required' => $data['quantity_required']]
        );

        return response()->json($ingredient->load('ingredient'), 201);
    }

    public function destroy(Product $product, ProductIngredient $productIngredient)
    {
        if ($productIngredient->product_id !== $product->id) {
            abort(404);
        }

        $productIngredient->delete();

        return response()->json(null, 204);
    }
}