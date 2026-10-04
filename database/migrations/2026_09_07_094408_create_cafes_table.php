<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cafes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])
                  ->default('pending');
            $table->timestamps();
            $table->decimal('tax_rate', 5, 2)->default(10.00);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cafes');
    }
};