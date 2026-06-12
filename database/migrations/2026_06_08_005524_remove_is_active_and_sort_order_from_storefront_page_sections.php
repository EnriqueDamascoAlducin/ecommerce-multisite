<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_page_sections', function (Blueprint $table) {
            $table->index('storefront_page_id');
            $table->dropIndex(['storefront_page_id', 'sort_order']);
            $table->dropColumn(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('storefront_page_sections', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('type');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            $table->index(['storefront_page_id', 'sort_order']);
            $table->dropIndex(['storefront_page_id']);
        });
    }
};
