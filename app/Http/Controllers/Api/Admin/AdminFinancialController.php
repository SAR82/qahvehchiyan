<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
use Illuminate\Http\Request;

class AdminFinancialController extends Controller
{
    public function summary(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $base = FinancialTransaction::whereBetween('occurred_at', [$data['from'], $data['to']]);

        $platformIncome = (clone $base)->whereNull('cafe_id')->where('type', 'income')->sum('amount');
        $cafesIncome = (clone $base)->whereNotNull('cafe_id')->where('type', 'income')->sum('amount');
        $cafesExpense = (clone $base)->whereNotNull('cafe_id')->where('type', 'expense')->sum('amount');

        return response()->json([
            'platform_revenue' => (float) $platformIncome,
            'cafes_total_income' => (float) $cafesIncome,
            'cafes_total_expense' => (float) $cafesExpense,
        ]);
    }

    public function perCafe(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return FinancialTransaction::whereNotNull('cafe_id')
            ->whereBetween('occurred_at', [$data['from'], $data['to']])
            ->selectRaw('cafe_id, type, SUM(amount) as total')
            ->groupBy('cafe_id', 'type')
            ->with('cafe:id,name')
            ->get()
            ->groupBy('cafe_id');
    }
}