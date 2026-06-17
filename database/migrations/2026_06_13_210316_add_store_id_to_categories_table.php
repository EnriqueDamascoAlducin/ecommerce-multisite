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
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('website_id')->constrained()->cascadeOnDelete();
        });

        // Backfill: cada categoría queda asignada a la tienda por defecto de su
        // website (is_default; en su defecto, la de menor sort_order).
        $defaultStoreByWebsite = DB::table('stores')
            ->orderBy('website_id')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'website_id'])
            ->groupBy('website_id')
            ->map(fn ($stores) => $stores->first()->id);

        foreach ($defaultStoreByWebsite as $websiteId => $storeId) {
            DB::table('categories')
                ->where('website_id', $websiteId)
                ->update(['store_id' => $storeId]);
        }

        // El slug pasa a ser único por tienda (permite el mismo slug en tiendas
        // distintas del mismo website). El FK de website_id se apoyaba en el
        // índice único compuesto, así que primero añadimos un índice simple para
        // poder soltarlo.
        Schema::table('categories', function (Blueprint $table) {
            $table->index('website_id');
            $table->dropUnique(['website_id', 'slug']);
            $table->unique(['store_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'slug']);
            $table->unique(['website_id', 'slug']);
            $table->dropIndex(['website_id']);
            $table->dropConstrainedForeignId('store_id');
        });
    }
};
