<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asignación (muchos a muchos) de etiquetas a productos.
     */
    public function up(): void
    {
        Schema::create('product_product_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_label_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'product_label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_product_label');
    }
};
