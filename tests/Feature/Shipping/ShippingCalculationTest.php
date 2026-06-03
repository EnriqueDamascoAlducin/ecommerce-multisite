<?php

use App\Domain\Shipping\ShippingMethodResolver;
use App\Domain\Shipping\ShippingRateCalculator;
use App\Models\ShippingMethod;
use App\Models\Store;
use App\Models\StoreShippingMethod;

beforeEach(function () {
    $this->store = Store::factory()->create();
    $this->resolver = app(ShippingMethodResolver::class);
    $this->calculator = app(ShippingRateCalculator::class);
});

/**
 * Habilita un método para la tienda con una tarifa base.
 *
 * @param  array<string, mixed>  $config
 */
function storeMethod(Store $store, ShippingMethod $method, array $config = [], float $rate = 0): StoreShippingMethod
{
    $ssm = StoreShippingMethod::factory()->create(array_merge([
        'store_id' => $store->id,
        'shipping_method_id' => $method->id,
        'is_active' => true,
    ], $config));

    $ssm->rates()->create(['min_subtotal' => 0, 'max_subtotal' => null, 'amount' => $rate]);

    return $ssm->load('method', 'rates');
}

test('flat rate returns the configured amount', function () {
    $method = ShippingMethod::factory()->create(['type' => 'flat_rate']);
    $ssm = storeMethod($this->store, $method, [], rate: 99);

    expect($this->calculator->amount($ssm, 500))->toBe('99.00');
});

test('free shipping type always costs zero', function () {
    $method = ShippingMethod::factory()->freeShipping()->create();
    $ssm = storeMethod($this->store, $method, [], rate: 99);

    expect($this->calculator->amount($ssm, 500))->toBe('0.00');
});

test('pickup type always costs zero', function () {
    $method = ShippingMethod::factory()->pickup()->create();
    $ssm = storeMethod($this->store, $method, [], rate: 50);

    expect($this->calculator->amount($ssm, 500))->toBe('0.00');
});

test('flat rate becomes free over a threshold', function () {
    $method = ShippingMethod::factory()->create(['type' => 'flat_rate']);
    $ssm = storeMethod($this->store, $method, ['free_over' => 999], rate: 99);

    expect($this->calculator->amount($ssm, 500))->toBe('99.00')
        ->and($this->calculator->amount($ssm, 1000))->toBe('0.00');
});

test('methods are filtered by subtotal restriction', function () {
    $method = ShippingMethod::factory()->create();
    storeMethod($this->store, $method, ['min_subtotal' => 1500], rate: 0);

    expect($this->resolver->availableForCart($this->store, 1000))->toHaveCount(0)
        ->and($this->resolver->availableForCart($this->store, 2000))->toHaveCount(1);
});

test('methods are filtered by destination country', function () {
    $method = ShippingMethod::factory()->create();
    storeMethod($this->store, $method, ['countries' => ['US']], rate: 99);

    expect($this->resolver->availableForCart($this->store, 500, 'MX'))->toHaveCount(0)
        ->and($this->resolver->availableForCart($this->store, 500, 'US'))->toHaveCount(1)
        ->and($this->resolver->availableForCart($this->store, 500, null))->toHaveCount(0);
});

test('inactive methods are not available', function () {
    $method = ShippingMethod::factory()->create(['is_active' => false]);
    storeMethod($this->store, $method, [], rate: 99);

    expect($this->resolver->availableForCart($this->store, 500))->toHaveCount(0);
});

test('a tiered rate matches the subtotal bracket', function () {
    $method = ShippingMethod::factory()->create(['type' => 'flat_rate']);
    $ssm = StoreShippingMethod::factory()->create([
        'store_id' => $this->store->id,
        'shipping_method_id' => $method->id,
    ]);
    $ssm->rates()->create(['min_subtotal' => 0, 'max_subtotal' => 499.99, 'amount' => 120, 'sort_order' => 0]);
    $ssm->rates()->create(['min_subtotal' => 500, 'max_subtotal' => null, 'amount' => 60, 'sort_order' => 1]);
    $ssm->load('method', 'rates');

    expect($this->calculator->amount($ssm, 300))->toBe('120.00')
        ->and($this->calculator->amount($ssm, 800))->toBe('60.00');
});
