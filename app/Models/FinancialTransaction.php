<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialTransaction extends Model
{
    protected $fillable = [
        'cafe_id', 'category_id', 'type', 'amount', 'description',
        'source_type', 'source_id', 'occurred_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'date',
        ];
    }

    public function category()
    {
        return $this->belongsTo(FinancialCategory::class, 'category_id');
    }

    public function cafe()
    {
        return $this->belongsTo(Cafe::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}