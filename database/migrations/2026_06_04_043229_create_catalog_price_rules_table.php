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
        Schema::create('catalog_price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->nullable()->constrained()->nullOnDelete(); // null = todos los sitios
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete(); // null = todo el catálogo
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('action')->default('percent'); // percent | fixed_amount | fixed_price
            $table->decimal('value', 12, 2)->default(0);
            $table->unsignedInteger('priority')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'website_id']);
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_price_rules');
    }
};
