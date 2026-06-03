<?php

use App\Models\Order;
use App\Models\Store;
use App\Models\Website;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Website::class)->constrained();
            $table->foreignIdFor(Store::class)->constrained();
            $table->foreignIdFor(Order::class)->constrained()->cascadeOnDelete();
            $table->string('number', 50)->unique();
            $table->string('status', 30)->default('pending');
            $table->string('carrier_code', 50)->nullable();
            $table->string('carrier_label', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->integer('total_qty')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
