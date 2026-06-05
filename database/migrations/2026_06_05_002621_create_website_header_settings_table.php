<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuración del encabezado por website (branding compartido por todas
     * sus tiendas). Fase 1: cintillo superior con texto o redes sociales y
     * colores propios. Los colores del header/mega menú se agregan en Fase 2.
     */
    public function up(): void
    {
        Schema::create('website_header_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('cintillo_enabled')->default(false);
            $table->string('cintillo_type')->default('text');
            $table->string('cintillo_text')->nullable();
            $table->string('cintillo_text_color')->default('#ffffff');
            $table->string('cintillo_background_color')->default('#111827');
            $table->json('cintillo_social')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_header_settings');
    }
};
