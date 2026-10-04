<?php

namespace App\Http\Controllers\Api;

use App\Models\CafeTable;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;


class TableController extends Controller
{
    public function index()
    {
        return CafeTable::where('is_active', true)->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:50'],
        ]);

        return response()->json(CafeTable::create($data), 201);
    }

    public function update(Request $request, CafeTable $table)
    {
        $data = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $table->update($data);

        return $table;
    }

}