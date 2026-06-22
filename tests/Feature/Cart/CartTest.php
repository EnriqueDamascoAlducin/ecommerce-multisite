<?php

use App\Models\CartItem;
use App\Models\InventorySource;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\Website;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true]);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    $this->source = InventorySource::factory()->default()->create();
});

/**
 * @return array{parent: Product, variant: Product}
 */
function hiddenVariantForCart(Store $store, InventorySource $source): array
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

test('a guest can add a product to the cart', function () {
    $product = sellableProduct($this->store, $this->source, 150);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2])
        ->assertRedirect();

    $this->assertDatabaseHas('cart_items', [
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => '150.00',
    ]);
    $this->assertDatabaseHas('carts', ['store_id' => $this->store->id, 'customer_id' => null]);
});

test('adding the same product accumulates quantity', function () {
    $product = sellableProduct($this->store, $this->source);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 3]);

    $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 4]);
});

test('stock is validated when adding', function () {
    $product = sellableProduct($this->store, $this->source, 100, stock: 2);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 5])
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
});

test('the cart page shows items and backend totals', function () {
    $product = sellableProduct($this->store, $this->source, 200);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 3]);

    $this->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/cart')
            ->has('items', 1)
            ->where('totals.total', '600.00')
            ->where('totals.items_count', 3));
});

test('a cart item quantity can be updated', function () {
    $product = sellableProduct($this->store, $this->source);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
    $item = CartItem::firstOrFail();

    $this->patch(route('cart.update', $item), ['quantity' => 5])->assertRedirect();

    expect($item->fresh()->quantity)->toBe(5);
});

test('updating quantity to zero removes the item', function () {
    $product = sellableProduct($this->store, $this->source);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);
    $item = CartItem::firstOrFail();

    $this->patch(route('cart.update', $item), ['quantity' => 0])->assertRedirect();

    $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
});

test('a cart item can be removed', function () {
    $product = sellableProduct($this->store, $this->source);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
    $item = CartItem::firstOrFail();

    $this->delete(route('cart.destroy', $item))->assertRedirect();

    $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
});

test('the unit price is refreshed to the current store price', function () {
    $product = sellableProduct($this->store, $this->source, 100);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);

    // El precio cambia tras agregar al carrito.
    $product->prices()->where('store_id', null)->update(['price' => 80]);

    $this->get(route('cart.index'))
        ->assertInertia(fn ($page) => $page->where('totals.total', '80.00'));

    $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'unit_price' => '80.00']);
});

test('a hidden product cannot be added to the cart', function () {
    $product = sellableProduct($this->store, $this->source);
    $product->update(['visibility' => 'hidden']);

    $this->post(route('cart.store'), ['product_id' => $product->id])
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
});

test('a hidden configurable variant can be added using a configured domain', function () {
    $host = 'catalogo-dinamico.test';
    StoreDomain::factory()->for($this->store)->create(['host' => $host]);
    ['variant' => $variant] = hiddenVariantForCart($this->store, $this->source);

    $this->post("http://{$host}/carrito", [
        'product_id' => $variant->id,
        'quantity' => 2,
    ])->assertSessionDoesntHaveErrors()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('cart_items', [
        'product_id' => $variant->id,
        'sku' => $variant->sku,
        'quantity' => 2,
    ]);
    $this->assertDatabaseHas('carts', ['store_id' => $this->store->id]);
});

test('a hidden variant is rejected when its configurable parent is inactive in the store', function () {
    ['parent' => $parent, 'variant' => $variant] = hiddenVariantForCart($this->store, $this->source);
    $parent->storeLinks()->where('store_id', $this->store->id)->update(['is_active' => false]);

    $this->post(route('cart.store'), ['product_id' => $variant->id])
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('cart_items', ['product_id' => $variant->id]);
});

test('a hidden variant is rejected when it is not assigned to the resolved store', function () {
    ['variant' => $variant] = hiddenVariantForCart($this->store, $this->source);
    $variant->storeLinks()->delete();

    $this->post(route('cart.store'), ['product_id' => $variant->id])
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('cart_items', ['product_id' => $variant->id]);
});

test('a hidden variant is rejected without an effective price', function () {
    ['variant' => $variant] = hiddenVariantForCart($this->store, $this->source);
    $variant->prices()->delete();

    $this->post(route('cart.store'), ['product_id' => $variant->id])
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('cart_items', ['product_id' => $variant->id]);
});

test('a hidden variant can be added to a store resolved by path prefix', function () {
    $host = 'catalogo-prefijo.test';
    StoreDomain::factory()->for($this->store)->create(['host' => $host]);
    $prefixedStore = Store::factory()->create([
        'website_id' => $this->website->id,
        'code' => 'outlet',
        'is_default' => false,
        'is_active' => true,
    ]);
    ['variant' => $variant] = hiddenVariantForCart($prefixedStore, $this->source);

    $this->post("http://{$host}/outlet/carrito", [
        'product_id' => $variant->id,
        'quantity' => 1,
    ])->assertSessionHas('success');

    $this->assertDatabaseHas('cart_items', [
        'product_id' => $variant->id,
        'sku' => $variant->sku,
    ]);
    $this->assertDatabaseHas('carts', ['store_id' => $prefixedStore->id]);
});

test('a guest cannot modify a cart item from another cart', function () {
    $foreignItem = CartItem::factory()->create();

    $this->delete(route('cart.destroy', $foreignItem))->assertForbidden();
});
