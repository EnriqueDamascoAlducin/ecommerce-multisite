<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices compuestos para acelerar las consultas de catálogo del storefront,
     * que filtran por tipo (productos raíz), estado y visibilidad.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['status', 'visibility'], 'products_status_visibility_index');
            $table->index(['type', 'parent_id'], 'products_type_parent_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_status_visibility_index');
            $table->dropIndex('products_type_parent_index');
        });
    }
};
