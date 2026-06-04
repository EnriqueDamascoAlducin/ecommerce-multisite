<?php

use App\Models\Cart;
use App\Models\CartPriceRule;
use App\Models\InventorySource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use App\Models\Website;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true, 'code' => 'demo']);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    $this->source = InventorySource::factory()->default()->create();

    $method = ShippingMethod::factory()->create(['code' => 'flat_rate', 'type' => 'flat_rate']);
    $ssm = StoreShippingMethod::factory()->create([
        'store_id' => $this->store->id,
        'shipping_method_id' => $method->id,
    ]);
    $ssm->rates()->create(['min_subtotal' => 0, 'max_subtotal' => null, 'amount' => 99]);
});

function addToCart(Product $product, int $qty = 1): void
{
    test()->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => $qty]);
}

function couponPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'guest@example.com',
        'payment_method' => 'offline',
        'shipping_method_code' => 'flat_rate',
        'billing_same' => '1',
        'shipping' => [
            'first_name' => 'Ana', 'last_name' => 'López', 'line1' => 'Calle 123',
            'city' => 'CDMX', 'state' => 'CDMX', 'postal_code' => '01000', 'country' => 'MX',
        ],
    ], $overrides);
}

test('a percentage coupon reduces the discount line', function () {
    $product = sellableProduct($this->store, $this->source, 200, stock: 10);
    addToCart($product, 2); // subtotal 400

    CartPriceRule::factory()->percent(10)->coupon('SAVE10')->create();
    $this->post(route('cart.coupon.apply'), ['code' => 'SAVE10'])->assertSessionHasNoErrors();

    $this->get(route('cart.index'))->assertInertia(fn ($page) => $page
        ->where('coupon', 'SAVE10')
        ->where('totals.discount', '40.00')
    );
});

test('a fixed coupon applies a flat discount', function () {
    $product = sellableProduct($this->store, $this->source, 200, stock: 10);
    addToCart($product, 1); // subtotal 200

    CartPriceRule::factory()->fixed(50)->coupon('MENOS50')->create();
    $this->post(route('cart.coupon.apply'), ['code' => 'MENOS50']);

    $this->get(route('cart.index'))->assertInertia(fn ($page) => $page->where('totals.discount', '50.00'));
});

test('an invalid coupon is rejected', function () {
    $product = sellableProduct($this->store, $this->source, 200, stock: 10);
    addToCart($product, 1);

    $this->post(route('cart.coupon.apply'), ['code' => 'NOEXISTE'])->assertSessionHas('error');
    expect(Cart::firstOrFail()->coupon_code)->toBeNull();
});

test('a coupon below the minimum subtotal is rejected', function () {
    $product = sellableProduct($this->store, $this->source, 100, stock: 10);
    addToCart($product, 1); // subtotal 100

    CartPriceRule::factory()->percent(10)->coupon('VIP')->create(['min_subtotal' => 500]);

    $this->post(route('cart.coupon.apply'), ['code' => 'VIP'])->assertSessionHas('error');
});

test('a free shipping coupon zeroes the shipping', function () {
    $product = sellableProduct($this->store, $this->source, 200, stock: 10);
    addToCart($product, 1);
    $this->post(route('cart.shipping'), ['shipping_method_code' => 'flat_rate']);

    CartPriceRule::factory()->freeShipping()->coupon('FREESHIP')->create();
    $this->post(route('cart.coupon.apply'), ['code' => 'FREESHIP']);

    $this->get(route('cart.index'))->assertInertia(fn ($page) => $page->where('totals.shipping', '0.00'));
});

test('an automatic rule applies without a coupon', function () {
    $product = sellableProduct($this->store, $this->source, 200, stock: 10);
    addToCart($product, 1); // subtotal 200

    CartPriceRule::factory()->fixed(25)->create(['min_subtotal' => 100]); // sin cupón = automática

    $this->get(route('cart.index'))->assertInertia(fn ($page) => $page->where('totals.discount', '25.00'));
});

test('placing an order consumes coupon usage and stores the code', function () {
    $product = sellableProduct($this->store, $this->source, 200, stock: 10);
    addToCart($product, 2); // subtotal 400

    $rule = CartPriceRule::factory()->percent(10)->coupon('SAVE10')->create(['usage_limit' => 5]);
    $this->post(route('cart.coupon.apply'), ['code' => 'SAVE10']);

    $this->post(route('checkout.store'), couponPayload())->assertRedirect();

    $order = Order::firstOrFail();
    expect($order->coupon_code)->toBe('SAVE10')
        ->and((string) $order->discount)->toBe('40.00')
        ->and($rule->fresh()->times_used)->toBe(1);
});

test('an expired coupon is rejected', function () {
    $product = sellableProduct($this->store, $this->source, 200, stock: 10);
    addToCart($product, 1);

    CartPriceRule::factory()->percent(10)->coupon('VIEJO')->create(['ends_at' => now()->subDay()]);

    $this->post(route('cart.coupon.apply'), ['code' => 'VIEJO'])->assertSessionHas('error');
});
