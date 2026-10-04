<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cafe_order_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained()->cascadeOnDelete();
            $table->date('counter_date');
            $table->unsignedInteger('last_number')->default(99);
            $table->timestamps();
    
            $table->unique(['cafe_id', 'counter_date']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('cafe_order_counters');
    }
};
