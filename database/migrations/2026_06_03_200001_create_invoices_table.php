<?php

use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('order_id')->constrained();
            $table->string('number');
            $table->string('status')->default(Invoice::STATUS_PENDING);
            $table->string('currency', 3)->default('MXN');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
