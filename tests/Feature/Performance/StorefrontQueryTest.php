<?php

use App\Models\Category;
use App\Models\InventorySource;
use App\Models\Store;
use App\Models\Website;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true]);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    $this->source = InventorySource::factory()->default()->create();
});

/**
 * Cuenta las consultas SQL ejecutadas durante el request dado.
 */
function countQueries(Closure $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $request();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function categoryWithProducts(Store $store, InventorySource $source, Category $category, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $product = sellableProduct($store, $source, 100);
        $product->categories()->attach($category->id);
    }
}

test('the category page query count does not grow with the number of products', function () {
    $category = Category::factory()->create(['website_id' => $this->website->id, 'slug' => 'ofertas', 'is_active' => true]);

    categoryWithProducts($this->store, $this->source, $category, 3);
    $few = countQueries(fn () => $this->get(route('storefront.category', 'ofertas'))->assertOk());

    categoryWithProducts($this->store, $this->source, $category, 12);
    $many = countQueries(fn () => $this->get(route('storefront.category', 'ofertas'))->assertOk());

    // El catálogo carga sus relaciones por adelantado: pasar de 3 a 15 productos
    // no debe disparar consultas adicionales por producto (sin N+1).
    expect($many - $few)->toBeLessThanOrEqual(2);
});

test('the home page stays within a bounded query budget', function () {
    for ($i = 0; $i < 12; $i++) {
        sellableProduct($this->store, $this->source, 100);
    }

    $queries = countQueries(fn () => $this->get(route('home'))->assertOk());

    expect($queries)->toBeLessThan(20);
});
