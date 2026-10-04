<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinancialTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = FinancialTransaction::where('cafe_id', $request->user()->cafe_id)
            ->with('category');

        if ($request->filled('from')) $query->whereDate('occurred_at', '>=', $request->date('from'));
        if ($request->filled('to')) $query->whereDate('occurred_at', '<=', $request->date('to'));
        if ($request->filled('type')) $query->where('type', $request->string('type'));

        return $query->latest('occurred_at')->paginate(30);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'category_name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:500'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $cafeId = $request->user()->cafe_id;

        $category = FinancialCategory::firstOrCreate(
            ['cafe_id' => $cafeId, 'type' => $data['type'], 'name' => $data['category_name']],
            ['is_system' => false]
        );

        $tx = FinancialTransaction::create([
            'cafe_id' => $cafeId,
            'category_id' => $category->id,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
            'source_type' => 'manual',
            'occurred_at' => $data['occurred_at'] ?? now()->toDateString(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($tx->load('category'), 201);
    }

    public function destroy(Request $request, FinancialTransaction $financialTransaction)
    {
        if ($financialTransaction->cafe_id !== $request->user()->cafe_id) {
            abort(404);
        }
        if ($financialTransaction->source_type !== 'manual') {
            return response()->json(['message' => 'این تراکنش به‌صورت خودکار ثبت شده و قابل حذف نیست.'], 422);
        }

        $financialTransaction->delete();
        return response()->json(null, 204);
    }

    public function details(Request $request, FinancialTransaction $financialTransaction)
        {
            if ($financialTransaction->cafe_id !== $request->user()->cafe_id) {
                abort(404);
            }

            $financialTransaction->load('category');

            $payload = [
                'id' => $financialTransaction->id,
                'type' => $financialTransaction->type,
                'amount' => $financialTransaction->amount,
                'category' => $financialTransaction->category->name,
                'source_type' => $financialTransaction->source_type,
                'description' => $financialTransaction->description,
                'occurred_at' => $financialTransaction->occurred_at,
                'created_at' => $financialTransaction->created_at,
            ];

            switch ($financialTransaction->source_type) {
                case 'payment':
                    $payment = \App\Models\Payment::with('order.items.product')->find($financialTransaction->source_id);
                    if ($payment && $payment->order) {
                        $payload['order'] = [
                            'id' => $payment->order->id,
                            'channel' => $payment->order->channel,
                            'method' => $payment->method,
                            'items' => $payment->order->items->map(fn ($item) => [
                                'product_name' => $item->product->name ?? '(محصول حذف‌شده)',
                                'quantity' => $item->quantity,
                                'unit_price' => $item->unit_price,
                            ]),
                        ];
                    }
                    break;

                case 'inventory_transaction':
                    $tx = \App\Models\InventoryTransaction::with('item')->find($financialTransaction->source_id);
                    if ($tx) {
                        $payload['inventory'] = [
                            'item_name' => $tx->item->name ?? '(کالای حذف‌شده)',
                            'quantity' => $tx->quantity,
                            'unit_cost' => $tx->unit_cost,
                            'supplier' => $tx->supplier,
                            'reference_number' => $tx->reference_number,
                        ];
                    }
                    break;

                case 'manual':
                    // چیزی بیشتر از description و occurred_at که از قبل توی payload هست نیاز نیست
                    break;

                // subscription_transaction و contribution سطح پلتفرمن (cafe_id=null)
                // و اصلاً به این endpoint (که فقط برای کافه‌هاست) نمی‌رسن.
            }

            return response()->json($payload);
        }

    public function summary(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);
    
        $cafeId = $request->user()->cafe_id;
        $base = FinancialTransaction::where('financial_transactions.cafe_id', $cafeId)
            ->whereBetween('financial_transactions.occurred_at', [$data['from'], $data['to']]);
    
        $totalIncome = (clone $base)->where('type', 'income')->sum('amount');
        $totalExpense = (clone $base)->where('type', 'expense')->sum('amount');
    
        $byCategory = (clone $base)
            ->join('financial_categories', 'financial_categories.id', '=', 'financial_transactions.category_id')
            ->selectRaw('financial_categories.name, financial_transactions.type, SUM(financial_transactions.amount) as total')
            ->groupBy('financial_categories.name', 'financial_transactions.type')
            ->get();
    
        return response()->json([
            'from' => $data['from'],
            'to' => $data['to'],
            'total_income' => (float) $totalIncome,
            'total_expense' => (float) $totalExpense,
            'net_profit' => (float) $totalIncome - (float) $totalExpense,
            'by_category' => $byCategory,
        ]);
    }
}