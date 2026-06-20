<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('storefront_page_store', function (Blueprint $table) {
            $table->foreignId('storefront_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->primary(['storefront_page_id', 'store_id']);
        });

        DB::table('storefront_pages')
            ->select(['id', 'store_id'])
            ->orderBy('id')
            ->chunkById(500, function ($pages): void {
                DB::table('storefront_page_store')->insertOrIgnore(
                    $pages->map(fn ($page) => [
                        'storefront_page_id' => $page->id,
                        'store_id' => $page->store_id,
                    ])->all(),
                );
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storefront_page_store');
    }
};
