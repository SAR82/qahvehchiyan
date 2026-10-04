<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCafe;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use BelongsToCafe;
    protected $fillable = ['cafe_id', 'name'];

    public function cafe()
    {
        return $this->belongsTo(Cafe::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}