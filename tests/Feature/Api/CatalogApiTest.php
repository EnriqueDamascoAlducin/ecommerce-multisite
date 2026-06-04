<?php

use App\Models\Category;
use App\Models\InventorySource;
use App\Models\Store;
use App\Models\Website;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true, 'code' => 'demo']);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    $this->source = InventorySource::factory()->default()->create();
});

test('the catalog lists active products', function () {
    $product = sellableProduct($this->store, $this->source, 250, stock: 10);

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonPath('data.0.sku', $product->sku)
        ->assertJsonPath('data.0.in_stock', true)
        ->assertJsonStructure(['data' => [['id', 'sku', 'name', 'slug', 'price', 'in_stock']], 'meta', 'links']);
});

test('a product can be fetched by slug', function () {
    $product = sellableProduct($this->store, $this->source, 250, stock: 10);

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.sku', $product->sku)
        ->assertJsonStructure(['data' => ['id', 'sku', 'name', 'description', 'gallery', 'attributes', 'categories']]);
});

test('a missing product returns 404', function () {
    $this->getJson('/api/v1/products/no-existe')->assertNotFound();
});

test('categories are listed for the resolved website', function () {
    Category::create(['website_id' => $this->website->id, 'name' => 'Electrónica', 'slug' => 'electronica', 'is_active' => true]);
    Category::create(['website_id' => $this->website->id, 'name' => 'Oculta', 'slug' => 'oculta', 'is_active' => false]);

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'electronica');
});
