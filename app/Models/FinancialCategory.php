<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialCategory extends Model
{
    protected $fillable = ['cafe_id', 'type', 'name', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function transactions()
    {
        return $this->hasMany(FinancialTransaction::class, 'category_id');
    }

    public function cafe()
    {
        return $this->belongsTo(Cafe::class);
    }
}