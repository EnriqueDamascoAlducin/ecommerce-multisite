<?php

use App\Models\InventorySource;
use App\Models\Order;
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

test('a bundle is added to the cart at the summed component price', function () {
    $a = sellableProduct($this->store, $this->source, 100, stock: 10);
    $b = sellableProduct($this->store, $this->source, 50, stock: 10);
    $bundle = bundleProduct($this->store, [[$a, 1], [$b, 2]]);

    $this->post(route('cart.store'), ['product_id' => $bundle->id, 'quantity' => 1])
        ->assertRedirect()
        ->assertSessionHas('success');

    // 100 + (50 × 2) = 200
    $this->assertDatabaseHas('cart_items', [
        'product_id' => $bundle->id,
        'quantity' => 1,
        'unit_price' => '200.00',
    ]);
});

test('a bundle cannot be added when a component is out of stock', function () {
    $a = sellableProduct($this->store, $this->source, 100, stock: 10);
    $b = sellableProduct($this->store, $this->source, 50, stock: 1);
    $bundle = bundleProduct($this->store, [[$a, 1], [$b, 2]]);

    $this->post(route('cart.store'), ['product_id' => $bundle->id, 'quantity' => 1])
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('cart_items', ['product_id' => $bundle->id]);
});

test('placing an order with a bundle reserves each component stock', function () {
    $a = sellableProduct($this->store, $this->source, 100, stock: 10);
    $b = sellableProduct($this->store, $this->source, 50, stock: 10);
    $bundle = bundleProduct($this->store, [[$a, 1], [$b, 2]]);

    $this->post(route('cart.store'), ['product_id' => $bundle->id, 'quantity' => 2]);

    $this->post(route('checkout.store'), [
        'email' => 'guest@example.com',
        'payment_method' => 'offline',
        'shipping_method_code' => 'flat_rate',
        'billing_same' => '1',
        'shipping' => [
            'first_name' => 'Ana',
            'last_name' => 'López',
            'line1' => 'Calle 123',
            'city' => 'CDMX',
            'state' => 'CDMX',
            'postal_code' => '01000',
            'country' => 'MX',
        ],
    ])->assertRedirect();

    $order = Order::firstOrFail();

    // 2 bundles → componente A: 2 × 1 = 2, componente B: 2 × 2 = 4
    expect($a->inventoryStocks()->first()->reserved_qty)->toBe(2);
    expect($b->inventoryStocks()->first()->reserved_qty)->toBe(4);

    // El bundle se guarda como una línea con snapshot de su contenido.
    $item = $order->items()->where('product_id', $bundle->id)->firstOrFail();
    expect($item->options['bundle'])->toHaveCount(2);
});
