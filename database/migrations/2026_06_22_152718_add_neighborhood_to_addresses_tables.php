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
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('neighborhood')->nullable()->after('line2');
        });

        Schema::table('order_addresses', function (Blueprint $table) {
            $table->string('neighborhood')->nullable()->after('line2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn('neighborhood');
        });

        Schema::table('order_addresses', function (Blueprint $table) {
            $table->dropColumn('neighborhood');
        });
    }
};
