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
        Schema::create('store_shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable(); // sobreescribe el nombre del método
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->decimal('free_over', 12, 2)->nullable(); // gratis si subtotal >= free_over
            $table->decimal('min_subtotal', 12, 2)->nullable(); // disponible desde
            $table->decimal('max_subtotal', 12, 2)->nullable(); // disponible hasta
            $table->json('countries')->nullable(); // ISO2 permitidos; null = todos
            $table->timestamps();

            $table->unique(['store_id', 'shipping_method_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_shipping_methods');
    }
};
