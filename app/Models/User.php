<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'cafe_id', 'phone', 'password', 'role', 'is_active',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function cafe()
    {
        return $this->belongsTo(Cafe::class);
    }

    public function ownedCafe()
    {
        return $this->hasOne(Cafe::class, 'owner_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'created_by');
    }
}