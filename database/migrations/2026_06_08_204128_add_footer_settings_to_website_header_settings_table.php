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
        Schema::table('website_header_settings', function (Blueprint $table) {
            $table->json('footer_settings')->nullable()->after('menu_background_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_header_settings', function (Blueprint $table) {
            $table->dropColumn('footer_settings');
        });
    }
};
