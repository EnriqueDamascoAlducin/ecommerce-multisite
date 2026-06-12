<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_stores', function (Blueprint $table) {
            $table->index(['store_id', 'is_active', 'product_id'], 'product_stores_store_active_product_index');
        });

        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->index(['attribute_id', 'product_id'], 'product_attribute_values_attribute_product_index');
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->index(['store_id', 'product_id'], 'product_prices_store_product_index');
        });
    }

    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropIndex('product_prices_store_product_index');
        });

        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->dropIndex('product_attribute_values_attribute_product_index');
        });

        Schema::table('product_stores', function (Blueprint $table) {
            $table->dropIndex('product_stores_store_active_product_index');
        });
    }
};
