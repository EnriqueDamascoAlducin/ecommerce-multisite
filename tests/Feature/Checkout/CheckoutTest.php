<?php

use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerAddress;
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

function checkoutPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'guest@example.com',
        'payment_method' => 'offline',
        'shipping_method_code' => 'flat_rate',
        'billing_same' => '1',
        'shipping' => [
            'first_name' => 'Ana',
            'last_name' => 'López',
            'line1' => 'Calle 123',
            'neighborhood' => 'San Ángel',
            'city' => 'CDMX',
            'state' => 'CDMX',
            'postal_code' => '01000',
            'country' => 'MX',
        ],
    ], $overrides);
}

/** @return array{parent: Product, variant: Product} */
function hiddenVariantForCheckout(Store $store, InventorySource $source): array
{
    $parent = sellableProduct($store, $source, 200);
    $parent->update(['type' => Product::TYPE_CONFIGURABLE]);

    $variant = sellableProduct($store, $source, 175);
    $variant->update([
        'parent_id' => $parent->id,
        'visibility' => 'hidden',
    ]);

    return compact('parent', 'variant');
}

test('a guest can place an order and it becomes pending payment', function () {
    $product = sellableProduct($this->store, $this->source, 200, stock: 10);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);

    $this->post(route('checkout.store'), checkoutPayload())->assertRedirect();

    $order = Order::firstOrFail();
    expect($order->status)->toBe(Order::STATUS_PENDING_PAYMENT)
        ->and($order->email)->toBe('guest@example.com')
        ->and((string) $order->subtotal)->toBe('400.00')
        ->and((string) $order->shipping_amount)->toBe('99.00')
        ->and((string) $order->total)->toBe('499.00');

    $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 2]);
    $this->assertDatabaseHas('order_addresses', ['order_id' => $order->id, 'type' => 'shipping', 'neighborhood' => 'San Ángel']);
    $this->assertDatabaseHas('order_addresses', ['order_id' => $order->id, 'type' => 'billing', 'neighborhood' => 'San Ángel']);
});

test('a guest can finish checkout with a hidden configurable variant', function () {
    ['variant' => $variant] = hiddenVariantForCheckout($this->store, $this->source);

    $this->post(route('cart.store'), [
        'product_id' => $variant->id,
        'quantity' => 2,
    ])->assertSessionHas('success');

    $response = $this->post(route('checkout.store'), checkoutPayload());
    $order = Order::query()->firstOrFail();
    $response->assertRedirect(route('checkout.success', $order));

    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_id' => $variant->id,
        'sku' => $variant->sku,
        'quantity' => 2,
    ]);
});

test('checkout rejects a hidden variant when its configurable parent becomes inactive', function () {
    ['parent' => $parent, 'variant' => $variant] = hiddenVariantForCheckout($this->store, $this->source);
    $this->post(route('cart.store'), ['product_id' => $variant->id, 'quantity' => 1]);
    $parent->update(['status' => Product::STATUS_INACTIVE]);

    $this->post(route('checkout.store'), checkoutPayload())
        ->assertRedirect(route('checkout.index'))
        ->assertSessionHas('error');

    expect(Order::query()->count())->toBe(0);
});

test('placing an order reserves stock', function () {
    $product = sellableProduct($this->store, $this->source, 100, stock: 10);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 3]);

    $this->post(route('checkout.store'), checkoutPayload());

    $stock = $product->inventoryStocks()->first();
    expect($stock->reserved_qty)->toBe(3)
        ->and($stock->available_qty)->toBe(7);

    $order = Order::firstOrFail();
    $this->assertDatabaseHas('stock_reservations', [
        'product_id' => $product->id,
        'quantity' => 3,
        'reference' => "order:{$order->id}",
        'status' => 'active',
    ]);
});

test('placing an order converts the cart and records initial history', function () {
    $product = sellableProduct($this->store, $this->source, 100);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);

    $this->post(route('checkout.store'), checkoutPayload());

    $order = Order::firstOrFail();
    expect(Cart::firstOrFail()->status)->toBe('converted');
    $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'to_status' => 'pending_payment', 'from_status' => null]);
});

test('the order number is prefixed per website', function () {
    $product = sellableProduct($this->store, $this->source, 100);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);

    $this->post(route('checkout.store'), checkoutPayload());

    expect(Order::firstOrFail()->number)->toStartWith('DEMO-');
});

test('checkout with an empty cart redirects to the cart', function () {
    $this->get(route('checkout.index'))->assertRedirect(route('cart.index'));
});

test('checkout fails the final stock validation when stock dropped', function () {
    $product = sellableProduct($this->store, $this->source, 100, stock: 5);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 5]);

    // El stock cae por debajo de lo pedido entre el carrito y el checkout.
    $product->inventoryStocks()->update(['physical_qty' => 2]);

    $this->post(route('checkout.store'), checkoutPayload())
        ->assertRedirect(route('checkout.index'))
        ->assertSessionHas('error');

    expect(Order::count())->toBe(0);
});

test('email is required to place an order', function () {
    $product = sellableProduct($this->store, $this->source, 100);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);

    $this->post(route('checkout.store'), checkoutPayload(['email' => '']))
        ->assertSessionHasErrors('email');
});

test('checkout only accepts addresses in mexico', function () {
    $product = sellableProduct($this->store, $this->source, 100);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);

    $payload = checkoutPayload();
    $payload['shipping']['country'] = 'US';

    $this->post(route('checkout.store'), $payload)
        ->assertSessionHasErrors('shipping.country');

    expect(Order::query()->count())->toBe(0);
});

test('checkout page includes customer addresses for logged in customers', function () {
    $customer = Customer::factory()->create(['website_id' => $this->website->id]);
    CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'first_name' => 'Luis',
        'last_name' => 'Pérez',
        'line1' => 'Av. Reforma 123',
        'city' => 'CDMX',
        'state' => 'CDMX',
        'postal_code' => '06600',
        'country' => 'MX',
        'is_default_shipping' => true,
    ]);

    $product = sellableProduct($this->store, $this->source, 100);

    $this->actingAs($customer, 'customer');
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);

    $this->get(route('checkout.index'))
        ->assertInertia(fn ($page) => $page
            ->component('storefront/checkout')
            ->has('addresses', 1)
            ->has('customer')
            ->where('customer.email', $customer->email)
            ->where('addresses.0.first_name', 'Luis')
        );
});

test('checkout with different billing address creates both addresses', function () {
    $product = sellableProduct($this->store, $this->source, 200, stock: 10);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);

    $payload = checkoutPayload([
        'billing_same' => '0',
        'billing' => [
            'first_name' => 'María',
            'last_name' => 'García',
            'line1' => 'Calle 456',
            'neighborhood' => 'Centro',
            'city' => 'Monterrey',
            'state' => 'NL',
            'postal_code' => '64000',
            'country' => 'MX',
        ],
    ]);

    $this->post(route('checkout.store'), $payload)->assertRedirect();

    $order = Order::firstOrFail();
    expect($order->billingAddress->first_name)->toBe('María')
        ->and($order->billingAddress->neighborhood)->toBe('Centro')
        ->and($order->shippingAddress->first_name)->toBe('Ana');
});

test('success page includes shipping address', function () {
    $product = sellableProduct($this->store, $this->source, 200, stock: 10);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);

    $this->post(route('checkout.store'), checkoutPayload());

    $order = Order::firstOrFail();

    $this->get(route('checkout.success', $order))
        ->assertInertia(fn ($page) => $page
            ->component('storefront/checkout-success')
            ->where('order.shipping_address.first_name', 'Ana')
            ->where('order.shipping_address.line1', 'Calle 123')
            ->where('order.shipping_address.neighborhood', 'San Ángel')
        );
});
