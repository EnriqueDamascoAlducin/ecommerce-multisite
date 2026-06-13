<?php

use App\Models\StorefrontPage;
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
        Schema::table('storefront_pages', function (Blueprint $table) {
            $table->string('template')->default('home')->after('slug');
        });

        // Existing non-home pages keep working under the "flexible" template,
        // which allows every section type they may already contain.
        DB::table('storefront_pages')
            ->where('slug', '!=', StorefrontPage::HOME)
            ->update(['template' => 'flexible']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('storefront_pages', function (Blueprint $table) {
            $table->dropColumn('template');
        });
    }
};
