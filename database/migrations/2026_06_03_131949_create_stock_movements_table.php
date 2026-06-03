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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_source_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // in | out | adjustment | reservation | release
            $table->integer('quantity'); // delta con signo
            $table->integer('balance_after'); // stock físico tras el movimiento
            $table->string('reason')->nullable();
            $table->string('reference')->nullable(); // p. ej. order:45, cart:12
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'inventory_source_id']);
            $table->index('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
