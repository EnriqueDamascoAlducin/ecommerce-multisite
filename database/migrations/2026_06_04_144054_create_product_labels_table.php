<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo reutilizable de etiquetas (badges) por website: texto y colores
     * personalizables para resaltar productos (p. ej. «Oferta», «Nuevo»).
     */
    public function up(): void
    {
        Schema::create('product_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('text');
            $table->string('text_color')->default('#ffffff');
            $table->string('background_color')->default('#111827');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['website_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_labels');
    }
};
