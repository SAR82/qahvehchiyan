<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\InventoryTransaction;
use App\Models\ProductIngredient;
use App\Services\FinancialLedgerService;
use App\Models\InventoryItem;
use App\Services\LowStockNotifier;


class PaymentController extends Controller
{
    public function store(Request $request, Order $order)
    {
        if ($order->cafe_id !== $request->user()->cafe_id) {
            abort(404);
        }

        $data = $request->validate([
            'method' => ['required', Rule::in(['cash', 'manual_card_reader', 'online_gateway'])],
        ]);

        $payment = DB::transaction(function () use ($order, $data, $request) {
            // قفل سفارش تا دو پرداخت هم‌زمان، مانده را دوبار نگیرند
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== 'pending') {
                abort(422, 'فقط سفارش در وضعیت pending قابل پرداخت است.');
            }

            $isFirstPayment = ! $order->hasBeenPaid();
            $amount = round((float) $order->total_amount - $order->paidTotal(), 2);

            if ($amount <= 0) {
                abort(422, 'این سفارش مانده‌ی قابل پرداخت ندارد.');
            }

            $payment = Payment::create([
                'order_id' => $order->id,
                'method' => $data['method'],
                'amount' => $amount,
                'status' => 'success',
            ]);

            $order->status = 'paid';
            $order->save();

            // انبار فقط یک بار کسر می‌شود؛ تغییرات بعدی اقلام در OrderController@update تعدیل می‌شوند
            if ($isFirstPayment) {
                $this->deductIngredientsForOrder($order, $request->user()->id);
            }

            app(FinancialLedgerService::class)->recordSale($payment);

            return $payment;
        });

        return response()->json($payment->load('order'), 201);
    }

        /**
     * برگشت وجه به مشتری، وقتی مبلغ سفارش بعد از پرداخت کم شده یا سفارش بعد از پرداخت لغو شده.
     * یک Payment با amount منفی ثبت می‌کند تا paid_so_far خودبه‌خود درست شود.
     */
    public function refund(Request $request, Order $order)
    {
        if ($order->cafe_id !== $request->user()->cafe_id) {
            abort(404);
        }

        $data = $request->validate([
            // برگشت از درگاه آنلاین باید از خود درگاه انجام شود، نه دستی
            'method' => ['required', Rule::in(['cash', 'manual_card_reader'])],
        ]);

        $refund = DB::transaction(function () use ($order, $data) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $paid = $order->paidTotal();

            $amount = $order->status === 'cancelled_after_payment'
                ? $paid
                : round($paid - (float) $order->total_amount, 2);

            if ($amount <= 0) {
                abort(422, 'این سفارش مبلغی برای برگشت ندارد.');
            }

            $refund = Payment::create([
                'order_id' => $order->id,
                'method' => $data['method'],
                'amount' => -$amount,
                'status' => 'success',
            ]);

            app(FinancialLedgerService::class)->recordRefund($refund);

            return $refund;
        });

        return response()->json($refund->load('order'), 201);
    }

    /**
     * برای هر آیتم سفارش، رسپی محصول را بررسی می‌کند و
     * به همان نسبت، مواد اولیه را از انبار کسر می‌کند.
     * اگر موجودی کافی نبود، تراکنش باز هم ثبت می‌شود ولی is_flagged=true می‌شود.
     */
    protected function deductIngredientsForOrder(Order $order, int $userId): void
    {
        $order->load('items');
    
        foreach ($order->items as $item) {
            if (! $item->product_id) {
                continue;
            }
    
            $ingredients = ProductIngredient::where('product_id', $item->product_id)->get();
    
            foreach ($ingredients as $recipe) {
                // با lockForUpdate ردیف موجودی رو قفل می‌کنیم تا دو پرداخت هم‌زمان
                // روی یه ماده‌ی اولیه‌ی مشترک، محاسبه‌ی همدیگه رو خراب نکنن
                $inventoryItem = InventoryItem::where('id', $recipe->ingredient_id)
                    ->lockForUpdate()
                    ->first();
    
                if (! $inventoryItem) {
                    continue;
                }
    
                $quantityToDeduct = $recipe->quantity_required * $item->quantity;
                $newStock = $inventoryItem->current_stock - $quantityToDeduct;
    
                InventoryTransaction::create([
                    'item_id' => $inventoryItem->id,
                    'type' => 'sale_deduction',
                    'quantity' => -$quantityToDeduct,
                    'reference_order_id' => $order->id,
                    'created_by' => $userId,
                    'is_flagged' => $newStock < 0,
                ]);
    
                $inventoryItem->current_stock = $newStock;
                $inventoryItem->save();
                
                app(LowStockNotifier::class)->checkAndNotify($inventoryItem);
            }
        }
    }
}