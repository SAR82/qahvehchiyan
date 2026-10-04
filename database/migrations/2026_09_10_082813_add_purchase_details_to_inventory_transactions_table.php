<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->nullable()->after('quantity');
            $table->string('supplier')->nullable()->after('unit_cost');
            $table->string('reference_number')->nullable()->after('supplier');
            $table->text('note')->nullable()->after('reference_number');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'supplier', 'reference_number', 'note']);
        });
    }
};