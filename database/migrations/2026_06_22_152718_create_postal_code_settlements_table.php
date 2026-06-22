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
        Schema::create('postal_code_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('postal_code', 5);
            $table->string('settlement');
            $table->string('settlement_type')->nullable();
            $table->string('municipality');
            $table->string('state');
            $table->string('city')->nullable();
            $table->string('zone')->nullable();
            $table->timestamps();

            $table->index('postal_code');
            $table->unique(['postal_code', 'settlement']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postal_code_settlements');
    }
};
