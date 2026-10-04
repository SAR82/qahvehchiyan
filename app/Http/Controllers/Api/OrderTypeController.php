<?php

namespace App\Http\Controllers\Api;

use App\Models\OrderType;
use App\Http\Controllers\Controller;

class OrderTypeController extends Controller
{
    public function index()
    {
        return OrderType::where('is_active', true)->get();
    }
}