<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->enum('channel', ['cashier', 'waiter']);
            $table->enum('status', [
                'pending', 'paid', 'cancelled_before_payment', 'cancelled_after_payment'
            ])->default('pending');
            $table->foreignId('order_type_id')->constrained('order_types')->onDelete('restrict');
            $table->foreignId('table_id')->nullable()->constrained('cafe_tables')->onDelete('restrict');
            $table->foreignId('discount_code_id')->nullable()->constrained('discount_codes')->onDelete('set null');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->boolean('is_offline_sync')->default(false);
            $table->string('client_uuid')->nullable()->unique();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};