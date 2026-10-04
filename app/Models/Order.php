<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use BelongsToCafe;
    protected $fillable = [
        'cafe_id', 'created_by', 'channel', 'status',
        'order_type_id', 'table_id',
        'discount_code_id', 'subtotal', 'discount_percentage', 'tax_percentage',
        'is_offline_sync', 'client_uuid', 'total_amount', 'order_number'
    ];

    protected function casts(): array
    {
        return [
            'is_offline_sync' => 'boolean',
            'subtotal' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function cafe()
    {
        return $this->belongsTo(Cafe::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function orderType()
    {
        return $this->belongsTo(OrderType::class);
    }

    public function table()
    {
        return $this->belongsTo(CafeTable::class, 'table_id');
    }

    public function discountCode()
    {
        return $this->belongsTo(DiscountCode::class);
    }

        /**
     * جمع خالص پرداخت‌های موفق (پرداخت‌ها منهای برگشت‌ها؛ برگشت‌ها amount منفی دارند).
     */
    public function paidTotal(): float
    {
        return (float) $this->payments()->where('status', 'success')->sum('amount');
    }

    /**
     * آیا تا به حال پرداخت مثبتی برای این سفارش ثبت شده؟
     * (یعنی انبار قبلاً برایش کسر شده)
     */
    public function hasBeenPaid(): bool
    {
        return $this->payments()->where('status', 'success')->where('amount', '>', 0)->exists();
    }
}