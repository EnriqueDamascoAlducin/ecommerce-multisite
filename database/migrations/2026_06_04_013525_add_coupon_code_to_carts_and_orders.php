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
        Schema::table('carts', function (Blueprint $table) {
            $table->string('coupon_code')->nullable()->after('shipping_method_code');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('coupon_code')->nullable()->after('shipping_method_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('coupon_code');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('coupon_code');
        });
    }
};
