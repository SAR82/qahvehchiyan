<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CafeSubscription extends Model
{
    protected $fillable = ['cafe_id', 'plan_id', 'status', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function cafe()
    {
        return $this->belongsTo(Cafe::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function transactions()
    {
        return $this->hasMany(SubscriptionTransaction::class);
    }
}