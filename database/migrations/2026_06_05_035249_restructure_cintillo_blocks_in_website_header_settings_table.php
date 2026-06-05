<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El cintillo pasa de un solo modo (texto o redes) a hasta 3 bloques
     * mezclables, más un control para mostrarlo u ocultarlo en mobile.
     */
    public function up(): void
    {
        Schema::table('website_header_settings', function (Blueprint $table) {
            $table->json('cintillo_blocks')->nullable()->after('cintillo_enabled');
            $table->boolean('cintillo_show_on_mobile')->default(true)->after('cintillo_blocks');
        });

        Schema::table('website_header_settings', function (Blueprint $table) {
            $table->dropColumn(['cintillo_type', 'cintillo_text', 'cintillo_social']);
        });
    }

    public function down(): void
    {
        Schema::table('website_header_settings', function (Blueprint $table) {
            $table->string('cintillo_type')->default('text')->after('cintillo_enabled');
            $table->string('cintillo_text')->nullable()->after('cintillo_type');
            $table->json('cintillo_social')->nullable()->after('cintillo_background_color');
        });

        Schema::table('website_header_settings', function (Blueprint $table) {
            $table->dropColumn(['cintillo_blocks', 'cintillo_show_on_mobile']);
        });
    }
};
