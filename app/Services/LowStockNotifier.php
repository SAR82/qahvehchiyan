<?php

namespace App\Services;

use App\Models\InventoryItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LowStockNotifier
{
    public function __construct(protected SmsService $sms)
    {
    }

    public function checkAndNotify(InventoryItem $item): void
    {
        $isLow = (float) $item->current_stock <= (float) $item->low_stock_threshold;

        if (! $isLow) {
            // موجودی دوباره بالا رفته: افت بعدی دوباره هشدار بدهد
            if ($item->low_stock_notified_at) {
                InventoryItem::withoutGlobalScopes()->whereKey($item->id)
                    ->update(['low_stock_notified_at' => null]);
            }
            return;
        }

        // فقط یک درخواست می‌تواند «پرچم» را از خالی به پر ببرد؛ پیامک تکراری نمی‌رود
        $claimed = InventoryItem::withoutGlobalScopes()->whereKey($item->id)
            ->whereNull('low_stock_notified_at')
            ->update(['low_stock_notified_at' => now()]);

        if ($claimed === 1 && ! $this->notifyOwner($item)) {
            // ارسال شکست خورد: پرچم را برمی‌داریم تا دفعه‌ی بعد دوباره تلاش شود
            InventoryItem::withoutGlobalScopes()->whereKey($item->id)
                ->update(['low_stock_notified_at' => null]);
        }
    }

    protected function notifyOwner(InventoryItem $item): bool
    {
        $templateId = config('services.smsir.low_stock_template_id');
        $cafe = $item->cafe;
        $owner = $cafe?->owner ?? $cafe?->users()->where('role', 'owner')->first();

        if (! $templateId || ! $owner || ! $owner->phone) {
            return true; // چیزی برای ارسال نیست؛ دوباره تلاش نکن
        }

        try {
            // اینجا مقدار متغیرهای قالب ساخته می‌شود
            return $this->sms->sendTemplate($owner->phone, $templateId, [
                'ITEM' => Str::limit($item->name, 15, ''),
                'CAFE' => Str::limit($cafe->name, 15, ''),
                'STOCK' => $this->withUnit($item->current_stock, $item->unit),
            ]);
        } catch (\Throwable $e) {
            Log::warning('low stock sms failed', ['item_id' => $item->id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    // «100.000» را «100» می‌کند و واحد را کنارش می‌گذارد
    protected function withUnit($value, string $unit): string
    {
        $n = rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');

        return Str::limit($n.' '.$unit, 12, '');
    }
}