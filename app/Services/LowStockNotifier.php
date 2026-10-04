<?php

namespace App\Services;

use App\Models\InventoryItem;

class LowStockNotifier
{
    public function __construct(protected SmsService $sms)
    {
    }

    /**
     * وضعیت موجودی یک کالا را نسبت به آستانه‌ی هشدار بررسی می‌کند.
     * - اگر موجودی به آستانه رسیده/زیرش رفته و قبلاً پیامکی برای این افت ارسال نشده، پیامک می‌فرستد.
     * - اگر موجودی دوباره بالای آستانه رفته، وضعیت را ریست می‌کند تا افت بعدی دوباره هشدار بدهد.
     */
    public function checkAndNotify(InventoryItem $item): void
    {
        $isLow = $item->current_stock <= $item->low_stock_threshold;

        if ($isLow && ! $item->low_stock_notified_at) {
            $this->notifyOwner($item);
            $item->low_stock_notified_at = now();
            $item->save();
            return;
        }

        if (! $isLow && $item->low_stock_notified_at) {
            $item->low_stock_notified_at = null;
            $item->save();
        }
    }

    protected function notifyOwner(InventoryItem $item): void
    {
        $owner = $item->cafe->owner ?? $item->cafe->users()->where('role', 'owner')->first();

        if (! $owner || ! $owner->phone) {
            return;
        }

        $message = "هشدار قهوه‌چیان ({$item->cafe->name}): موجودی «{$item->name}» به {$item->current_stock} {$item->unit} رسیده (آستانه: {$item->low_stock_threshold} {$item->unit}).";
        $this->sms->send($owner->phone, $message);
    }
}