<?php

use App\Domain\Catalog\ProductPricingService;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Store;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->pricing = app(ProductPricingService::class);
});

test('uses the base price when there is no store override', function () {
    $product = Product::factory()->create();
    ProductPrice::factory()->for($product)->create(['store_id' => null, 'price' => 100]);

    expect($this->pricing->priceFor($product->fresh())['effective_price'])->toBe('100.00');
});

test('uses the store override price for that store', function () {
    $product = Product::factory()->create();
    $store = Store::factory()->create();
    ProductPrice::factory()->for($product)->create(['store_id' => null, 'price' => 100]);
    ProductPrice::factory()->for($product)->create(['store_id' => $store->id, 'price' => 80]);

    expect($this->pricing->priceFor($product->fresh(), $store->id)['price'])->toBe('80.00')
        ->and($this->pricing->priceFor($product->fresh())['price'])->toBe('100.00');
});

test('an active special price becomes the effective price', function () {
    $product = Product::factory()->create();
    ProductPrice::factory()->for($product)->create([
        'store_id' => null,
        'price' => 100,
        'special_price' => 70,
        'special_price_from' => Carbon::yesterday(),
        'special_price_to' => Carbon::tomorrow(),
    ]);

    $result = $this->pricing->priceFor($product->fresh());

    expect($result['is_special'])->toBeTrue()
        ->and($result['effective_price'])->toBe('70.00');
});

test('an expired special price is ignored', function () {
    $product = Product::factory()->create();
    ProductPrice::factory()->for($product)->create([
        'store_id' => null,
        'price' => 100,
        'special_price' => 70,
        'special_price_from' => Carbon::now()->subDays(10),
        'special_price_to' => Carbon::yesterday(),
    ]);

    $result = $this->pricing->priceFor($product->fresh());

    expect($result['is_special'])->toBeFalse()
        ->and($result['effective_price'])->toBe('100.00');
});
