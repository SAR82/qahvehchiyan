<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderTypeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('order_types')->insert([
            ['key' => 'dine_in',  'label' => 'سالن',     'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'takeaway', 'label' => 'بیرون‌بر',  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'courier',  'label' => 'پیک',      'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'snapp',    'label' => 'اسنپ',      'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}