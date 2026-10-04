<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\FinancialLedgerService;
use App\Services\LowStockNotifier;

class InventoryTransactionController extends Controller
{
    public function index(Request $request)
    {
        $cafeId = $request->user()->cafe_id;

        $query = InventoryTransaction::query()
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_transactions.item_id')
            ->where('inventory_items.cafe_id', $cafeId)
            ->with('item')
            ->select('inventory_transactions.*');

        if ($request->has('item_id')) {
            $query->where('inventory_transactions.item_id', $request->integer('item_id'));
        }

        return $query->latest('inventory_transactions.created_at')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_id' => ['required', Rule::exists('inventory_items', 'id')],
            'type' => ['required', Rule::in([
                'purchase', 'return', 'adjustment', 'sale_deduction', 'waste', 'damage', 'manual_out',
            ])],
            'quantity' => ['required', 'numeric'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $item = InventoryItem::findOrFail($data['item_id']);

        if ($data['type'] === 'adjustment') {
            // برای تنظیم دستی، مقدار وارد شده = موجودی نهایی مطلوب است
            $quantity = $data['quantity'] - $item->current_stock;
        } else {
            $decreasingTypes = ['sale_deduction', 'waste', 'damage', 'manual_out'];
            $quantity = $data['quantity'];

            if (in_array($data['type'], $decreasingTypes) && $quantity > 0) {
                $quantity = -$quantity;
            }
        }

        $transaction = DB::transaction(function () use ($item, $data, $quantity, $request) {
            $newStock = $item->current_stock + $quantity;

            $transaction = InventoryTransaction::create([
                'item_id' => $item->id,
                'type' => $data['type'],
                'quantity' => $quantity,
                'unit_cost' => $data['unit_cost'] ?? null,
                'supplier' => $data['supplier'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
                'is_flagged' => $newStock < 0,
            ]);

            $item->current_stock = $newStock;
            $item->save();
            
            app(LowStockNotifier::class)->checkAndNotify($item);

            $transaction->setRelation('item', $item);
            app(FinancialLedgerService::class)->recordInventoryPurchase($transaction);

            return $transaction;
        });

        return response()->json($transaction->load('item'), 201);
    }
}