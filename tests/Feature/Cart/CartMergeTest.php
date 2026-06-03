<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\InventorySource;
use App\Models\Store;
use App\Models\Website;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true]);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    $this->source = InventorySource::factory()->default()->create();
});

test('the guest cart is merged into the customer cart on login', function () {
    $product = sellableProduct($this->store, $this->source, 100);

    $customer = Customer::factory()->create([
        'website_id' => $this->website->id,
        'email' => 'merge@example.com',
        'password' => Hash::make('secret123'),
    ]);

    // Como invitado, agrega un producto (se crea carrito de invitado en sesión).
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
    $this->assertDatabaseHas('carts', ['customer_id' => null]);

    // Inicia sesión: el carrito de invitado se fusiona en el del cliente.
    $this->post('/cuenta/login', ['email' => 'merge@example.com', 'password' => 'secret123'])
        ->assertRedirect(route('customer.account'));

    $customerCart = Cart::where('customer_id', $customer->id)->firstOrFail();

    $this->assertDatabaseHas('cart_items', [
        'cart_id' => $customerCart->id,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);
    // El carrito de invitado ya no existe.
    $this->assertDatabaseMissing('carts', ['customer_id' => null]);
});

test('quantities are summed when both carts hold the same product', function () {
    $product = sellableProduct($this->store, $this->source, 100, stock: 50);

    $customer = Customer::factory()->create([
        'website_id' => $this->website->id,
        'email' => 'merge2@example.com',
        'password' => Hash::make('secret123'),
    ]);

    // Carrito previo del cliente con 3 unidades.
    $customerCart = Cart::factory()->create([
        'store_id' => $this->store->id,
        'customer_id' => $customer->id,
        'session_token' => null,
    ]);
    CartItem::factory()->for($customerCart)->create([
        'product_id' => $product->id,
        'quantity' => 3,
        'unit_price' => '100.00',
    ]);

    // Como invitado agrega 2 del mismo producto.
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);

    $this->post('/cuenta/login', ['email' => 'merge2@example.com', 'password' => 'secret123'])
        ->assertRedirect();

    $this->assertDatabaseHas('cart_items', [
        'cart_id' => $customerCart->id,
        'product_id' => $product->id,
        'quantity' => 5,
    ]);
});
