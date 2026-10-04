<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('financial_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->string('name');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
    
            $table->unique(['cafe_id', 'type', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_categories');
    }
};
