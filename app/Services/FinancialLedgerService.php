<?php

namespace App\Services;

use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\SubscriptionTransaction;
use App\Models\Contribution;

class FinancialLedgerService
{
    public function recordSale(Payment $payment): FinancialTransaction
    {
        $order = $payment->order;
        $category = $this->systemCategory($order->cafe_id, 'income', 'فروش');

        return FinancialTransaction::create([
            'cafe_id' => $order->cafe_id,
            'category_id' => $category->id,
            'type' => 'income',
            'amount' => $payment->amount,
            'description' => "فروش سفارش #" . ($order->order_number ?? $order->id),
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'occurred_at' => now()->toDateString(),
            'created_by' => $order->created_by,
        ]);
    }

        /**
     * برگشت وجه به مشتری؛ به‌صورت هزینه ثبت می‌شود تا سود خالص درست بماند.
     * $refund یک Payment با amount منفی است.
     */
    public function recordRefund(Payment $refund): FinancialTransaction
    {
        $order = $refund->order;
        $category = $this->systemCategory($order->cafe_id, 'expense', 'بازگشت وجه');

        return FinancialTransaction::create([
            'cafe_id' => $order->cafe_id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount' => abs($refund->amount),
            'description' => "بازگشت وجه سفارش #" . ($order->order_number ?? $order->id),
            'source_type' => 'payment',
            'source_id' => $refund->id,
            'occurred_at' => now()->toDateString(),
            'created_by' => $order->created_by,
        ]);
    }

    public function recordInventoryPurchase(InventoryTransaction $tx): ?FinancialTransaction
    {
        if ($tx->type !== 'purchase' || ! $tx->unit_cost) {
            return null;
        }

        $cafeId = $tx->item->cafe_id;
        $category = $this->systemCategory($cafeId, 'expense', 'خرید انبار');
        $amount = abs($tx->quantity) * $tx->unit_cost;

        return FinancialTransaction::create([
            'cafe_id' => $cafeId,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount' => $amount,
            'description' => "خرید {$tx->item->name}" . ($tx->supplier ? " از {$tx->supplier}" : ''),
            'source_type' => 'inventory_transaction',
            'source_id' => $tx->id,
            'occurred_at' => now()->toDateString(),
            'created_by' => $tx->created_by,
        ]);
    }

    public function recordSubscriptionRevenue(SubscriptionTransaction $subTx): FinancialTransaction
    {
        $category = $this->systemCategory(null, 'income', 'درآمد اشتراک');

        return FinancialTransaction::create([
            'cafe_id' => null,
            'category_id' => $category->id,
            'type' => 'income',
            'amount' => $subTx->amount,
            'description' => "اشتراک کافه #{$subTx->cafeSubscription->cafe_id} - پلن {$subTx->plan->name}",
            'source_type' => 'subscription_transaction',
            'source_id' => $subTx->id,
            'occurred_at' => $subTx->paid_at?->toDateString() ?? now()->toDateString(),
        ]);
    }

    public function recordContribution(Contribution $contribution): FinancialTransaction
    {
        $categoryName = $contribution->type === 'donation' ? 'کمک مالی' : 'سرمایه‌گذاری';
        $category = $this->systemCategory(null, 'income', $categoryName);

        return FinancialTransaction::create([
            'cafe_id' => null,
            'category_id' => $category->id,
            'type' => 'income',
            'amount' => $contribution->amount,
            'description' => "{$categoryName} از {$contribution->name}",
            'source_type' => 'contribution',
            'source_id' => $contribution->id,   
            'occurred_at' => now()->toDateString(),
        ]);
    }

    protected function systemCategory(?int $cafeId, string $type, string $name): FinancialCategory
    {
        return FinancialCategory::firstOrCreate(
            ['cafe_id' => $cafeId, 'type' => $type, 'name' => $name],
            ['is_system' => true]
        );
    }
}