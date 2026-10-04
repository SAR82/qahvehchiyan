<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use BelongsToCafe;
    protected $fillable = ['cafe_id', 'category_id', 'name', 'price', 'is_active', 'product_type', 'is_tax_exempt', 'is_discountable'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'is_tax_exempt' => 'boolean',
            'is_discountable' => 'boolean',
        ];
    }

    public function cafe()
    {
        return $this->belongsTo(Cafe::class);
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function ingredients()
    {
        return $this->hasMany(ProductIngredient::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}