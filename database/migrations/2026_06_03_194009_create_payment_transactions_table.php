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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway'); // offline, mercadopago, ...
            $table->string('type')->default('payment'); // payment, refund
            $table->string('status')->default('pending'); // pending, paid, failed, cancelled, refunded
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('MXN');

            // Identificadores externos del gateway y dedupe idempotente.
            $table->string('gateway_transaction_id')->nullable(); // p. ej. id del pago en MP
            $table->string('reference')->nullable(); // p. ej. id de la preferencia
            $table->string('idempotency_key')->nullable()->unique();

            $table->json('payload')->nullable(); // respuesta cruda del gateway
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['gateway', 'gateway_transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
