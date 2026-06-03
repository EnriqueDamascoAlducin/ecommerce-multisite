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
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_source_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->string('reference')->nullable(); // p. ej. cart:12, order:45
            $table->string('status')->default('active'); // active | released | consumed
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'inventory_source_id']);
            $table->index('reference');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
