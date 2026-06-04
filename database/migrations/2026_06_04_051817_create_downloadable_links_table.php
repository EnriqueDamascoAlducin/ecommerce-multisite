<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Archivos descargables asociados a un producto tipo downloadable.
     */
    public function up(): void
    {
        Schema::create('downloadable_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path'); // ruta relativa en el disco "downloads"
            $table->string('original_name')->nullable();
            $table->unsignedInteger('max_downloads')->nullable(); // null = ilimitado
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downloadable_links');
    }
};
