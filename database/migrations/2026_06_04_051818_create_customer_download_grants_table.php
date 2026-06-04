<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permisos de descarga otorgados a un cliente al pagarse una orden con
     * productos descargables. Cada fila controla los usos restantes de un enlace.
     */
    public function up(): void
    {
        Schema::create('customer_download_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('downloadable_link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title'); // snapshot
            $table->unsignedInteger('max_downloads')->nullable(); // snapshot (null = ilimitado)
            $table->unsignedInteger('downloads_used')->default(0);
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();

            // Una orden otorga cada enlace una sola vez (idempotente).
            $table->unique(['order_id', 'downloadable_link_id']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_download_grants');
    }
};
