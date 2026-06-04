<?php

use App\Domain\Catalog\ProductPricingService;
use App\Models\CatalogPriceRule;
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
    $this->pricing = app(ProductPricingService::class);
});

test('a percentage catalog rule lowers the effective price', function () {
    $product = sellableProduct($this->store, $this->source, 100);
    CatalogPriceRule::factory()->percent(20)->create();

    $price = $this->pricing->priceFor($product, $this->store->id);

    expect($price['effective_price'])->toBe('80.00')
        ->and($price['is_special'])->toBeTrue()
        ->and($price['price'])->toBe('100.00');
});

test('a fixed price rule sets the price', function () {
    $product = sellableProduct($this->store, $this->source, 100);
    CatalogPriceRule::factory()->fixedPrice(70)->create();

    expect($this->pricing->priceFor($product, $this->store->id)['effective_price'])->toBe('70.00');
});

test('a category-scoped rule only applies to products in that category', function () {
    $category = Category::create(['website_id' => $this->website->id, 'name' => 'Ofertas', 'slug' => 'ofertas', 'is_active' => true]);

    $inCategory = sellableProduct($this->store, $this->source, 100);
    $inCategory->categories()->attach($category->id);
    $outside = sellableProduct($this->store, $this->source, 100);

    CatalogPriceRule::factory()->percent(20)->create(['category_id' => $category->id]);

    expect($this->pricing->priceFor($inCategory, $this->store->id)['effective_price'])->toBe('80.00')
        ->and($this->pricing->priceFor($outside, $this->store->id)['is_special'])->toBeFalse();
});

test('a rule for another website does not apply', function () {
    $other = Website::factory()->create(['code' => 'otro']);
    $product = sellableProduct($this->store, $this->source, 100);

    CatalogPriceRule::factory()->percent(20)->create(['website_id' => $other->id]);

    expect($this->pricing->priceFor($product, $this->store->id)['is_special'])->toBeFalse();
});

test('an expired rule does not apply', function () {
    $product = sellableProduct($this->store, $this->source, 100);
    CatalogPriceRule::factory()->percent(20)->create(['ends_at' => now()->subDay()]);

    expect($this->pricing->priceFor($product, $this->store->id)['is_special'])->toBeFalse();
});

test('the lowest of special price and catalog rule wins', function () {
    $product = sellableProduct($this->store, $this->source, 100);
    $product->prices()->where('store_id', null)->update(['special_price' => 90]); // especial vigente a 90

    CatalogPriceRule::factory()->percent(20)->create(); // 100 -> 80

    expect($this->pricing->priceFor($product->load('prices'), $this->store->id)['effective_price'])->toBe('80.00');
});

test('an inactive rule does not apply', function () {
    $product = sellableProduct($this->store, $this->source, 100);
    CatalogPriceRule::factory()->percent(20)->create(['is_active' => false]);

    expect($this->pricing->priceFor($product, $this->store->id)['is_special'])->toBeFalse();
});
