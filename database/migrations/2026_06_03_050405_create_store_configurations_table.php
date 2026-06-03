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
        Schema::create('store_configurations', function (Blueprint $table) {
            $table->id();
            // scope: global | website | store. scope_id = 0 para global.
            $table->string('scope', 20)->default('global');
            $table->unsignedBigInteger('scope_id')->default(0);
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'scope_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_configurations');
    }
};
