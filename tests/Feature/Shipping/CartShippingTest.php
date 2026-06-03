<?php

use App\Models\InventorySource;
use App\Models\ShippingMethod;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use App\Models\Website;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true]);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    $this->source = InventorySource::factory()->default()->create();

    // Tarifa fija $99 habilitada para la tienda.
    $this->flat = ShippingMethod::factory()->create(['code' => 'flat_rate', 'type' => 'flat_rate']);
    $ssm = StoreShippingMethod::factory()->create([
        'store_id' => $this->store->id,
        'shipping_method_id' => $this->flat->id,
    ]);
    $ssm->rates()->create(['min_subtotal' => 0, 'max_subtotal' => null, 'amount' => 99]);
});

test('the cart lists available shipping options', function () {
    $product = sellableProduct($this->store, $this->source, 100);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);

    $this->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('shippingOptions', 1)
            ->where('shippingOptions.0.code', 'flat_rate')
            ->where('shippingOptions.0.amount', '99.00'));
});

test('selecting a shipping method adds it to the total', function () {
    $product = sellableProduct($this->store, $this->source, 200);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);

    $this->post(route('cart.shipping'), ['shipping_method_code' => 'flat_rate'])->assertRedirect();

    $this->get(route('cart.index'))
        ->assertInertia(fn ($page) => $page
            ->where('selectedShipping', 'flat_rate')
            ->where('totals.shipping', '99.00')
            ->where('totals.total', '299.00'));
});

test('an unavailable shipping method is rejected', function () {
    $premium = ShippingMethod::factory()->create(['code' => 'premium', 'type' => 'flat_rate']);
    $ssm = StoreShippingMethod::factory()->create([
        'store_id' => $this->store->id,
        'shipping_method_id' => $premium->id,
        'min_subtotal' => 5000,
    ]);
    $ssm->rates()->create(['min_subtotal' => 0, 'max_subtotal' => null, 'amount' => 200]);

    $product = sellableProduct($this->store, $this->source, 100);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);

    $this->post(route('cart.shipping'), ['shipping_method_code' => 'premium'])
        ->assertSessionHas('error');
});

test('a selected method is dropped when it stops being available', function () {
    $product = sellableProduct($this->store, $this->source, 100, stock: 50);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 20]); // subtotal 2000
    $this->post(route('cart.shipping'), ['shipping_method_code' => 'flat_rate']);

    // Restringimos el método a subtotales altos y bajamos la cantidad.
    StoreShippingMethod::where('shipping_method_id', $this->flat->id)->update(['min_subtotal' => 5000]);
    $item = App\Models\CartItem::firstOrFail();
    $this->patch(route('cart.update', $item), ['quantity' => 1]); // subtotal 100

    $this->get(route('cart.index'))
        ->assertInertia(fn ($page) => $page->where('selectedShipping', null)->where('totals.shipping', '0.00'));
});
