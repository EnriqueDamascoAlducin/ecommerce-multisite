<?php

use App\Domain\Catalog\BundleService;
use App\Models\InventorySource;
use App\Models\Store;
use App\Models\Website;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true]);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    $this->source = InventorySource::factory()->default()->create();
    $this->bundles = app(BundleService::class);
});

test('a dynamic bundle price is the sum of its components', function () {
    $a = sellableProduct($this->store, $this->source, 100);
    $b = sellableProduct($this->store, $this->source, 50);

    $bundle = bundleProduct($this->store, [[$a, 1], [$b, 2]]);

    $price = $this->bundles->priceFor($bundle, $this->store->id);

    // 100 + (50 × 2) = 200
    expect($price['effective_price'])->toBe('200.00');
});

test('a dynamic bundle reflects component special prices', function () {
    $a = sellableProduct($this->store, $this->source, 100);
    $a->basePrice()->update(['special_price' => 80]);
    $b = sellableProduct($this->store, $this->source, 50);

    $bundle = bundleProduct($this->store, [[$a, 1], [$b, 1]]);

    $price = $this->bundles->priceFor($bundle, $this->store->id);

    expect($price['is_special'])->toBeTrue();
    expect($price['price'])->toBe('150.00');
    expect($price['effective_price'])->toBe('130.00');
});

test('a fixed bundle uses its own price regardless of components', function () {
    $a = sellableProduct($this->store, $this->source, 100);
    $b = sellableProduct($this->store, $this->source, 100);

    $bundle = bundleProduct($this->store, [[$a, 1], [$b, 1]], priceType: 'fixed', fixedPrice: 150);

    $price = $this->bundles->priceFor($bundle, $this->store->id);

    expect($price['effective_price'])->toBe('150.00');
});

test('a bundle can be fulfilled only when every component has stock', function () {
    $a = sellableProduct($this->store, $this->source, 100, stock: 10);
    $b = sellableProduct($this->store, $this->source, 50, stock: 1);

    // b requiere 2 por bundle pero solo hay 1 en stock.
    $bundle = bundleProduct($this->store, [[$a, 1], [$b, 2]]);

    expect($this->bundles->canFulfill($bundle, 1))->toBeFalse();

    // Reponiendo el componente escaso, ya se puede surtir.
    $b->inventoryStocks()->first()->update(['physical_qty' => 4]);

    expect($this->bundles->canFulfill($bundle->fresh()->load('bundleItems.product.inventoryStocks'), 1))->toBeTrue();
});

test('an empty bundle cannot be fulfilled', function () {
    $bundle = bundleProduct($this->store, []);

    expect($this->bundles->canFulfill($bundle, 1))->toBeFalse();
});

test('the components list is exposed for the storefront', function () {
    $a = sellableProduct($this->store, $this->source, 100);
    $b = sellableProduct($this->store, $this->source, 50);

    $bundle = bundleProduct($this->store, [[$a, 1], [$b, 3]]);

    $components = $this->bundles->componentsFor($bundle);

    expect($components)->toHaveCount(2);
    expect($components[1]['quantity'])->toBe(3);
    expect($components[1]['sku'])->toBe($b->sku);
});
