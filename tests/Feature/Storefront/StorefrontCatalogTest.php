<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Website;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true]);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
});

/**
 * Crea un producto activo y con precio base en la tienda actual.
 */
function publishedProduct(Store $store, array $attributes = [], float $basePrice = 100): Product
{
    $product = Product::factory()->create(array_merge([
        'status' => Product::STATUS_ACTIVE,
        'visibility' => 'both',
    ], $attributes));

    $product->prices()->create(['store_id' => null, 'price' => $basePrice]);
    $product->storeLinks()->create(['store_id' => $store->id, 'is_active' => true]);

    return $product;
}

test('the home shows products active in the store', function () {
    publishedProduct($this->store);
    publishedProduct($this->store);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('storefront/home')->has('featured', 2));
});

test('the home excludes products not enabled in the store', function () {
    publishedProduct($this->store);

    // Producto activo pero NO vinculado a la tienda.
    $orphan = Product::factory()->create();
    $orphan->prices()->create(['store_id' => null, 'price' => 50]);

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page->has('featured', 1));
});

test('a category lists its products', function () {
    $category = Category::factory()->create(['website_id' => $this->website->id, 'slug' => 'ofertas']);
    $product = publishedProduct($this->store);
    $product->categories()->attach($category->id);

    publishedProduct($this->store); // no está en la categoría

    $this->get(route('storefront.category', 'ofertas'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('storefront/category')->has('products.data', 1));
});

test('an unknown category returns 404', function () {
    $this->get(route('storefront.category', 'no-existe'))->assertNotFound();
});

test('the product page shows the store-specific price', function () {
    $product = publishedProduct($this->store, [], 100);
    // Override de precio para la tienda.
    $product->prices()->create(['store_id' => $this->store->id, 'price' => 79.90]);

    $this->get(route('storefront.product', $product->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/product')
            ->where('product.price.effective_price', '79.90'));
});

test('a product not active in the store returns 404', function () {
    $product = Product::factory()->create();
    $product->prices()->create(['store_id' => null, 'price' => 10]);
    // sin product_stores activo

    $this->get(route('storefront.product', $product->slug))->assertNotFound();
});

test('a hidden product is not reachable on the storefront', function () {
    $product = publishedProduct($this->store, ['visibility' => 'hidden']);

    $this->get(route('storefront.product', $product->slug))->assertNotFound();
});

test('an inactive product is not reachable', function () {
    $product = publishedProduct($this->store, ['status' => Product::STATUS_INACTIVE]);

    $this->get(route('storefront.product', $product->slug))->assertNotFound();
});
