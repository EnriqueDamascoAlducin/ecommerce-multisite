<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Producto vendible en una tienda: activo, con precio base, habilitado en la
 * tienda y con stock físico gestionado. Reutilizado por los tests de carrito.
 */
function sellableProduct(\App\Models\Store $store, \App\Models\InventorySource $source, float $price = 100, int $stock = 10): \App\Models\Product
{
    $product = \App\Models\Product::factory()->create([
        'status' => \App\Models\Product::STATUS_ACTIVE,
        'visibility' => 'both',
    ]);

    $product->prices()->create(['store_id' => null, 'price' => $price]);
    $product->storeLinks()->create(['store_id' => $store->id, 'is_active' => true]);
    $product->inventoryStocks()->create([
        'inventory_source_id' => $source->id,
        'physical_qty' => $stock,
        'reserved_qty' => 0,
        'manage_stock' => true,
    ]);

    return $product;
}
