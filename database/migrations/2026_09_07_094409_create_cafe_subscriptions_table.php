<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cafe_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')
                  ->constrained('cafes')
                  ->onDelete('cascade');
            $table->foreignId('plan_id')
                  ->constrained('subscription_plans')
                  ->onDelete('restrict');
            $table->enum('status', ['active', 'expired', 'pending_payment'])
                  ->default('pending_payment');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cafe_subscriptions');
    }
};