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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_token')->nullable(); // carrito de invitado
            $table->string('status')->default('active'); // active | converted | abandoned
            $table->string('currency', 3)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'customer_id']);
            $table->index(['store_id', 'session_token']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
