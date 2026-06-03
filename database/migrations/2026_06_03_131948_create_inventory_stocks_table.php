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
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_source_id')->constrained()->cascadeOnDelete();
            $table->integer('physical_qty')->default(0);
            $table->integer('reserved_qty')->default(0);
            $table->boolean('manage_stock')->default(true);
            $table->boolean('allow_backorders')->default(false);
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'inventory_source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};
