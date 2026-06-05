<?php

use App\Domain\Storefront\ProductSectionResolver;
use App\Models\InventorySource;
use App\Models\Product;
use App\Models\Store;
use App\Models\StorefrontPageSection;
use App\Models\Website;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true]);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    $this->source = InventorySource::factory()->default()->create();
    $this->resolver = app(ProductSectionResolver::class);
});

test('returns selected products by ids preserving order', function () {
    $productA = sellableProduct($this->store, $this->source, 100);
    $productB = sellableProduct($this->store, $this->source, 200);
    $productC = sellableProduct($this->store, $this->source, 300);

    $section = StorefrontPageSection::factory()->make([
        'settings' => ['product_ids' => [$productC->id, $productA->id]],
    ]);

    $result = $this->resolver->resolve($section, $this->store->id);

    expect($result)->toHaveCount(2);
    expect($result[0]->id)->toBe($productC->id);
    expect($result[1]->id)->toBe($productA->id);
});

test('returns fallback latest products when product_ids is empty', function () {
    $products = collect();
    for ($i = 0; $i < 5; $i++) {
        $products->push(sellableProduct($this->store, $this->source, 100));
    }

    $section = StorefrontPageSection::factory()->make([
        'settings' => ['product_ids' => []],
    ]);

    $result = $this->resolver->resolve($section, $this->store->id);

    expect($result)->toHaveCount(5);
});

test('returns fallback when product_ids is not set', function () {
    for ($i = 0; $i < 3; $i++) {
        sellableProduct($this->store, $this->source, 100);
    }

    $section = StorefrontPageSection::factory()->make([
        'settings' => ['title' => 'Test'],
    ]);

    $result = $this->resolver->resolve($section, $this->store->id);

    expect($result)->toHaveCount(3);
});

test('excludes inactive store products from selected ids', function () {
    $active = sellableProduct($this->store, $this->source, 100);
    $inactive = Product::factory()->create(['status' => Product::STATUS_ACTIVE]);
    $inactive->storeLinks()->create(['store_id' => $this->store->id, 'is_active' => false]);

    $section = StorefrontPageSection::factory()->make([
        'settings' => ['product_ids' => [$active->id, $inactive->id]],
    ]);

    $result = $this->resolver->resolve($section, $this->store->id);

    expect($result)->toHaveCount(1);
    expect($result[0]->id)->toBe($active->id);
});

test('respects limit for selected products', function () {
    $products = collect();
    for ($i = 0; $i < 5; $i++) {
        $products->push(sellableProduct($this->store, $this->source, 100));
    }

    $ids = $products->pluck('id')->all();

    $section = StorefrontPageSection::factory()->make([
        'settings' => ['product_ids' => $ids],
    ]);

    $result = $this->resolver->resolve($section, $this->store->id, 3);

    expect($result)->toHaveCount(3);
});
