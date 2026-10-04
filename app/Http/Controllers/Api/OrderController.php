<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\ProductIngredient;
use App\Services\LowStockNotifier;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::query()
            ->with('items.product', 'creator', 'orderType', 'table', 'discountCode')
            ->withSum(['payments as paid_total' => fn ($q) => $q->where('status', 'success')], 'amount');

        if ($request->has('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->has('channel')) {
            $query->where('channel', $request->string('channel'));
        }

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->string('date'));
        }

        return $query->latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['cashier', 'waiter'])],
            'order_type_id' => ['required', Rule::exists('order_types', 'id')->where('is_active', true)],
            'table_id' => [
                'nullable',
                Rule::exists('cafe_tables', 'id')->where('cafe_id', $request->user()->cafe_id),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'discount_code' => ['nullable', 'string'],
            'client_uuid' => ['nullable', 'string', 'unique:orders,client_uuid'],
        ]);

        $dineInTypeId = \App\Models\OrderType::where('key', 'dine_in')->value('id');

        if ($data['order_type_id'] == $dineInTypeId && empty($data['table_id'])) {
            return response()->json([
                'message' => 'برای سفارش سالن باید میز انتخاب شود.',
                'errors' => ['table_id' => ['فیلد میز الزامی است.']],
            ], 422);
        }

        if ($data['order_type_id'] != $dineInTypeId && !empty($data['table_id'])) {
            return response()->json([
                'message' => 'میز فقط برای سفارش سالن مجاز است.',
                'errors' => ['table_id' => ['این نوع سفارش نباید میز داشته باشد.']],
            ], 422);
        }

        $discountCode = null;
        if (!empty($data['discount_code'])) {
            $discountCode = DiscountCode::where('cafe_id', $request->user()->cafe_id)
                ->where('code', $data['discount_code'])
                ->where('is_active', true)
                ->first();

            if (!$discountCode) {
                return response()->json([
                    'message' => 'کد تخفیف نامعتبر یا غیرفعال است.',
                    'errors' => ['discount_code' => ['کد تخفیف نامعتبر است.']],
                ], 422);
            }
        }

        $order = DB::transaction(function () use ($data, $request, $discountCode) {
            $itemsToCreate = [];
            $lineItems = [];
            
            foreach ($data['items'] as $line) {
                $product = Product::findOrFail($line['product_id']);
            
                $itemsToCreate[] = [
                    'product_id' => $product->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $product->price,
                ];
            
                $lineItems[] = ['product' => $product, 'quantity' => $line['quantity']];
            }
            
            $totals = $this->calculateTotals($lineItems, $request->user()->cafe, $discountCode);

            $orderNumber = $this->nextOrderNumber($request->user()->cafe_id);

            $order = Order::create([
                'created_by' => $request->user()->id,
                'channel' => $data['channel'],
                'status' => 'pending',
                'order_type_id' => $data['order_type_id'],
                'table_id' => $data['table_id'] ?? null,
                'discount_code_id' => $discountCode?->id,
                'subtotal' => $totals['subtotal'],
                'discount_percentage' => $totals['discount_percentage'],
                'tax_percentage' => $totals['tax_percentage'],
                'total_amount' => $totals['total'],
                'client_uuid' => $data['client_uuid'] ?? null,
                'order_number' => $orderNumber,
            ]);

            $order->items()->createMany($itemsToCreate);

            return $order;
        });

        return response()->json($order->load('items.product', 'orderType', 'table', 'discountCode'), 201);
    }

    public function update(Request $request, Order $order)
    {
        if ($order->cafe_id !== $request->user()->cafe_id) {
            abort(404);
        }

        if (in_array($order->status, ['cancelled_before_payment', 'cancelled_after_payment'])) {
            return response()->json(['message' => 'سفارش لغوشده قابل ویرایش نیست.'], 422);
        }

        $data = $request->validate([
            'order_type_id' => ['required', Rule::exists('order_types', 'id')->where('is_active', true)],
            'table_id' => [
                'nullable',
                Rule::exists('cafe_tables', 'id')->where('cafe_id', $request->user()->cafe_id),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'discount_code' => ['nullable', 'string'],
        ]);

        $dineInTypeId = \App\Models\OrderType::where('key', 'dine_in')->value('id');

        if ($data['order_type_id'] == $dineInTypeId && empty($data['table_id'])) {
            return response()->json([
                'message' => 'برای سفارش سالن باید میز انتخاب شود.',
                'errors' => ['table_id' => ['فیلد میز الزامی است.']],
            ], 422);
        }

        if ($data['order_type_id'] != $dineInTypeId && !empty($data['table_id'])) {
            return response()->json([
                'message' => 'میز فقط برای سفارش سالن مجاز است.',
                'errors' => ['table_id' => ['این نوع سفارش نباید میز داشته باشد.']],
            ], 422);
        }

        $discountCode = null;
        if (!empty($data['discount_code'])) {
            $discountCode = DiscountCode::where('cafe_id', $request->user()->cafe_id)
                ->where('code', $data['discount_code'])
                ->where('is_active', true)
                ->first();

            if (!$discountCode) {
                return response()->json([
                    'message' => 'کد تخفیف نامعتبر یا غیرفعال است.',
                    'errors' => ['discount_code' => ['کد تخفیف نامعتبر است.']],
                ], 422);
            }
        }

        DB::transaction(function () use ($order, $data, $request, $discountCode) {
            $itemsToCreate = [];
            $lineItems = [];
            
            foreach ($data['items'] as $line) {
                $product = Product::findOrFail($line['product_id']);
            
                $itemsToCreate[] = [
                    'product_id' => $product->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $product->price,
                ];
            
                $lineItems[] = ['product' => $product, 'quantity' => $line['quantity']];
            }
            
            $totals = $this->calculateTotals($lineItems, $request->user()->cafe, $discountCode);

            // اگر سفارش قبلاً پرداخت شده، انبار برایش کسر شده و باید اختلاف اقلام تعدیل شود
            $wasPaid = $order->hasBeenPaid();
            $oldItems = $order->items()->get();

            $order->items()->delete();
            $order->items()->createMany($itemsToCreate);

            if ($wasPaid) {
                $this->reconcileInventory(
                    $order,
                    $request->user()->id,
                    $oldItems,
                    $order->items()->get()
                );
            }

            // فاکتور قبلی با اقلام/مبلغ جدید نمی‌خواند؛ حذف می‌شود تا با دکمه‌ی «فاکتور» دوباره صادر شود
            if ($order->invoice) {
                if ($order->invoice->pdf_path) {
                    @unlink(storage_path('app/public/' . $order->invoice->pdf_path));
                }
                $order->invoice->delete();
            }

            $order->update([
                'order_type_id' => $data['order_type_id'],
                'table_id' => $data['table_id'] ?? null,
                'discount_code_id' => $discountCode?->id,
                'subtotal' => $totals['subtotal'],
                'discount_percentage' => $totals['discount_percentage'],
                'tax_percentage' => $totals['tax_percentage'],
                'total_amount' => $totals['total'],
            ]);
        });

        $order->refresh();
        $paidSoFar = $order->paidTotal();
        $balance = round((float) $order->total_amount - $paidSoFar, 2);

        // وضعیت باید با مانده هماهنگ باشد، وگرنه سفارش بدهکار «paid» می‌ماند و پرداخت رد می‌شود
        if ($balance > 0 && $order->status === 'paid') {
            $order->update(['status' => 'pending']);
        } elseif ($balance <= 0 && $order->status === 'pending' && $paidSoFar > 0) {
            $order->update(['status' => 'paid']);
        }

        return response()->json([
            'order' => $order->load('items.product', 'orderType', 'table', 'discountCode'),
            'paid_so_far' => $paidSoFar,
            'balance_due' => $balance > 0 ? $balance : 0,
            'refund_due' => $balance < 0 ? abs($balance) : 0,
        ]);
    }

    /**
     * جمع خام اقلام → اعمال تخفیف → اعمال مالیات کافه → مبلغ نهایی.
     */
    protected function calculateTotals(array $lineItems, $cafe, ?DiscountCode $discountCode): array
    {
        $discountPercentage = $discountCode?->percentage ?? 0;
        $taxPercentage = $cafe->tax_rate;
        $discountRatio = $discountPercentage / 100;

        $subtotal = 0;        // جمع خام
        $discountTotal = 0;   // مجموع تخفیف (فقط محصولات مشمول)
        $taxBase = 0;         // مبنای مالیات: محصولات مشمول مالیات، بعد از تخفیف خودشان

        foreach ($lineItems as $line) {
            $product = $line['product'];
            $lineTotal = $product->price * $line['quantity'];

            $lineDiscount = $product->is_discountable ? $lineTotal * $discountRatio : 0;
            $lineNet = $lineTotal - $lineDiscount;

            $subtotal += $lineTotal;
            $discountTotal += $lineDiscount;

            if (! $product->is_tax_exempt) {
                $taxBase += $lineNet;
            }
        }

        $taxAmount = $taxBase * $taxPercentage / 100;
        $total = $subtotal - $discountTotal + $taxAmount;

        return [
            'subtotal' => round($subtotal, 2),
            'discount_percentage' => $discountPercentage,
            'tax_percentage' => $taxPercentage,
            'total' => round($total, 2),
        ];
    }


        /**
     * بعد از ویرایش اقلام یک سفارشِ قبلاً پرداخت‌شده، فقط اختلاف مصرف را
     * به‌صورت تراکنش تعدیلی روی انبار اعمال می‌کند.
     */
    protected function reconcileInventory(Order $order, int $userId, $oldItems, $newItems): void
    {
        $old = $this->ingredientNeeds($oldItems);
        $new = $this->ingredientNeeds($newItems);

        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $ingredientId) {
            // delta مثبت یعنی مصرف بیشتر شده، منفی یعنی مصرف کمتر (برگشت به انبار)
            $delta = ($new[$ingredientId] ?? 0) - ($old[$ingredientId] ?? 0);

            if (abs($delta) < 0.0005) {
                continue;
            }

            $inventoryItem = InventoryItem::where('id', $ingredientId)->lockForUpdate()->first();

            if (! $inventoryItem) {
                continue;
            }

            $signedQty = -$delta; // منفی = کسر از انبار
            $newStock = (float) $inventoryItem->current_stock + $signedQty;

            InventoryTransaction::create([
                'item_id' => $inventoryItem->id,
                'type' => 'adjustment',
                'quantity' => $signedQty,
                'reference_order_id' => $order->id,
                'created_by' => $userId,
                'note' => 'تعدیل بر اثر ویرایش سفارش #' . ($order->order_number ?? $order->id),
                'is_flagged' => $signedQty < 0 && $newStock < 0,
            ]);

            $inventoryItem->current_stock = $newStock;
            $inventoryItem->save();

            app(LowStockNotifier::class)->checkAndNotify($inventoryItem);
        }
    }

    /**
     * مجموع مواد اولیه‌ی لازم برای مجموعه‌ای از اقلام: [ingredient_id => quantity]
     */
    protected function ingredientNeeds($items): array
    {
        $needs = [];

        foreach ($items as $item) {
            if (! $item->product_id) {
                continue;
            }

            $recipes = ProductIngredient::where('product_id', $item->product_id)->get();

            foreach ($recipes as $recipe) {
                $needs[$recipe->ingredient_id] = ($needs[$recipe->ingredient_id] ?? 0)
                    + ((float) $recipe->quantity_required * (int) $item->quantity);
            }
        }

        return $needs;
    }
    protected function nextOrderNumber(int $cafeId): int
    {
        $today = now()->toDateString();

        DB::table('cafe_order_counters')->insertOrIgnore([
            'cafe_id' => $cafeId,
            'counter_date' => $today,
            'last_number' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = DB::table('cafe_order_counters')
            ->where('cafe_id', $cafeId)
            ->where('counter_date', $today)
            ->lockForUpdate()
            ->first();

        $nextNumber = $counter->last_number + 1;

        DB::table('cafe_order_counters')
            ->where('id', $counter->id)
            ->update(['last_number' => $nextNumber, 'updated_at' => now()]);

        return $nextNumber;
    }

    public function show(Request $request, Order $order)
    {
        $this->authorizeSameCafe($request, $order);

        return $order->load('items.product', 'payments', 'invoice', 'orderType', 'table', 'discountCode');
    }

    public function cancel(Request $request, Order $order)
    {
        $this->authorizeSameCafe($request, $order);

        if ($order->status === 'paid' || ($order->status === 'pending' && $order->paidTotal() > 0)) {
            $order->status = 'cancelled_after_payment';
        } elseif ($order->status === 'pending') {
            $order->status = 'cancelled_before_payment';
        } else {
            return response()->json([
                'message' => 'این سفارش قابل لغو نیست.',
            ], 422);
        }

        $order->save();

        return $order;
    }

    protected function authorizeSameCafe(Request $request, Order $order): void
    {
        if ($order->cafe_id !== $request->user()->cafe_id) {
            abort(404);
        }
    }
}