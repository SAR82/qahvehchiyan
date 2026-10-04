<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\Request;

class InventoryItemController extends Controller
{
    public function index()
    {
        return InventoryItem::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'low_stock_threshold' => ['required', 'numeric', 'min:0'],
        ]);

        // موجودی اولیه همیشه صفر است؛ برای شارژ اولیه باید تراکنش purchase ثبت شود
        $data['current_stock'] = 0;

        $item = InventoryItem::create($data);

        return response()->json($item, 201);
    }

    public function show(InventoryItem $inventoryItem)
    {
        return $inventoryItem;
    }

    public function update(Request $request, InventoryItem $inventoryItem)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'unit' => ['sometimes', 'required', 'string', 'max:50'],
            'low_stock_threshold' => ['sometimes', 'required', 'numeric', 'min:0'],
        ]);

        $inventoryItem->update($data);

        return $inventoryItem;
    }

    public function lowStockAlerts(Request $request)
    {
        $items = InventoryItem::where('cafe_id', $request->user()->cafe_id)
            ->whereColumn('current_stock', '<=', 'low_stock_threshold')
            ->get(['id', 'name', 'unit', 'current_stock', 'low_stock_threshold']);

        return response()->json($items);
    }

    public function destroy(InventoryItem $inventoryItem)
    {
        $inventoryItem->delete();

        return response()->json(null, 204);
    }
}