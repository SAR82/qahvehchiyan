<?php

namespace App\Models;


use App\Models\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use BelongsToCafe;
    protected $fillable = ['cafe_id', 'name', 'unit', 'current_stock', 'low_stock_threshold'];

protected function casts(): array
{
    return [
        'current_stock' => 'decimal:3',
        'low_stock_threshold' => 'decimal:3',
    ];
}

    public function cafe()
    {
        return $this->belongsTo(Cafe::class);
    }

    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class, 'item_id');
    }
}