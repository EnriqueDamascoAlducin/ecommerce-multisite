<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de grupos de clientes por website para segmentación (estilo
     * Magento): cada cliente pertenece a un grupo.
     */
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('description')->nullable();
            $table->string('color')->default('#6366f1');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['website_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
    }
};
