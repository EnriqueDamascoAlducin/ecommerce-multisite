<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuración de pasarelas de pago por website: habilitada, modo
     * (sandbox/live) y credenciales (encriptadas en la capa de modelo).
     */
    public function up(): void
    {
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('gateway'); // mercadopago | openpay | offline
            $table->boolean('is_enabled')->default(false);
            $table->string('mode')->default('sandbox'); // sandbox | live
            $table->text('credentials')->nullable(); // JSON encriptado
            $table->timestamps();

            // Una configuración por pasarela y sitio.
            $table->unique(['website_id', 'gateway']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};
