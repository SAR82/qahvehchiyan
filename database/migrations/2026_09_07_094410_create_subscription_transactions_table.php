<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_subscription_id')
                  ->constrained('cafe_subscriptions')
                  ->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->string('gateway_ref')->nullable();
            $table->enum('status', ['pending', 'success', 'failed'])
                  ->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_transactions');
    }
};