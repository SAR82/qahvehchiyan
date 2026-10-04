<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('cafe_id')
                  ->references('id')->on('cafes')
                  ->onDelete('cascade');
        });

        Schema::table('cafes', function (Blueprint $table) {
            $table->foreign('owner_id')
                  ->references('id')->on('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['cafe_id']);
        });

        Schema::table('cafes', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
        });
    }
};