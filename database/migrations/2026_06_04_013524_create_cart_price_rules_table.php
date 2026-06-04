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
        Schema::create('cart_price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->nullable()->constrained()->nullOnDelete(); // null = todos los sitios
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('coupon_code')->nullable(); // null = regla automática (sin cupón)
            $table->string('action')->default('percent'); // percent | fixed | free_shipping
            $table->decimal('value', 12, 2)->default(0); // % o monto fijo según action
            $table->decimal('min_subtotal', 12, 2)->nullable(); // condición de subtotal mínimo
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_limit')->nullable(); // límite global de usos
            $table->unsignedInteger('times_used')->default(0);
            $table->timestamps();

            $table->index('coupon_code');
            $table->index(['is_active', 'coupon_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_price_rules');
    }
};
