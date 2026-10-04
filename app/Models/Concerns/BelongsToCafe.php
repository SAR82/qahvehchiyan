<?php

namespace App\Models\Concerns;

use App\Models\Scopes\CafeScope;
use Illuminate\Support\Facades\Auth;

trait BelongsToCafe
{
    protected static function bootBelongsToCafe(): void
    {
        static::addGlobalScope(new CafeScope);

        static::creating(function ($model) {
            if (! $model->cafe_id && Auth::check() && Auth::user()->cafe_id) {
                $model->cafe_id = Auth::user()->cafe_id;
            }
        });
    }
}