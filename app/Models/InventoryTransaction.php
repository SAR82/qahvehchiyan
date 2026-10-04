<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    protected $fillable = [
        'item_id', 'type', 'quantity', 'unit_cost', 'supplier', 'reference_number', 'note',
        'reference_order_id', 'created_by', 'is_flagged',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'is_flagged' => 'boolean',
        ];
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'reference_order_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}