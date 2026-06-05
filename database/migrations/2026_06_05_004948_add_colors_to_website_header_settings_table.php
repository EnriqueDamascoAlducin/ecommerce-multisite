<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Colores opcionales del encabezado y mega menú (null = usar el estilo por
     * defecto del storefront, conservando el modo oscuro).
     */
    public function up(): void
    {
        Schema::table('website_header_settings', function (Blueprint $table) {
            $table->string('header_text_color')->nullable()->after('cintillo_social');
            $table->string('header_background_color')->nullable()->after('header_text_color');
            $table->string('menu_text_color')->nullable()->after('header_background_color');
            $table->string('menu_background_color')->nullable()->after('menu_text_color');
        });
    }

    public function down(): void
    {
        Schema::table('website_header_settings', function (Blueprint $table) {
            $table->dropColumn([
                'header_text_color',
                'header_background_color',
                'menu_text_color',
                'menu_background_color',
            ]);
        });
    }
};
