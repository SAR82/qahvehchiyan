<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_transactions', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('cafe_subscription_id')
                  ->constrained('subscription_plans')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_transactions', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn('plan_id');
        });
    }
};