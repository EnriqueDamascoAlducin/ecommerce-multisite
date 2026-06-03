<?php

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);
});

test('a super admin can list products', function () {
    $this->get(route('admin.products.index'))->assertOk();
});

test('a super admin can create a simple product with a base price', function () {
    $this->post(route('admin.products.store'), [
        'sku' => 'SKU-001',
        'name' => 'Camiseta',
        'status' => 'active',
        'visibility' => 'both',
        'price' => '199.90',
    ])->assertRedirect(route('admin.products.index'));

    $this->assertDatabaseHas('products', ['sku' => 'SKU-001', 'slug' => 'camiseta']);

    $product = Product::where('sku', 'SKU-001')->firstOrFail();
    $this->assertDatabaseHas('product_prices', [
        'product_id' => $product->id,
        'store_id' => null,
        'price' => '199.90',
    ]);
});

test('the slug is generated automatically from the name', function () {
    $this->post(route('admin.products.store'), [
        'sku' => 'SKU-XYZ',
        'name' => 'Producto De Prueba',
        'status' => 'active',
        'visibility' => 'both',
        'price' => '10',
    ]);

    $this->assertDatabaseHas('products', ['sku' => 'SKU-XYZ', 'slug' => 'producto-de-prueba']);
});

test('the sku must be unique', function () {
    Product::factory()->create(['sku' => 'DUP']);

    $this->post(route('admin.products.store'), [
        'sku' => 'DUP',
        'name' => 'Otro',
        'status' => 'active',
        'visibility' => 'both',
        'price' => '10',
    ])->assertSessionHasErrors('sku');
});

test('a product can be activated and priced per store', function () {
    $store = Store::factory()->create();

    $this->post(route('admin.products.store'), [
        'sku' => 'SKU-STORE',
        'name' => 'Producto Tienda',
        'status' => 'active',
        'visibility' => 'both',
        'price' => '100',
        'stores' => [
            ['store_id' => $store->id, 'is_active' => '1', 'price' => '80'],
        ],
    ])->assertRedirect();

    $product = Product::where('sku', 'SKU-STORE')->firstOrFail();

    $this->assertDatabaseHas('product_stores', [
        'product_id' => $product->id,
        'store_id' => $store->id,
        'is_active' => true,
    ]);
    $this->assertDatabaseHas('product_prices', [
        'product_id' => $product->id,
        'store_id' => $store->id,
        'price' => '80.00',
    ]);
});

test('a super admin can update a product', function () {
    $product = Product::factory()->create();

    $this->put(route('admin.products.update', $product), [
        'sku' => $product->sku,
        'name' => 'Nombre Nuevo',
        'status' => 'inactive',
        'visibility' => 'catalog',
        'price' => '50',
    ])->assertRedirect(route('admin.products.index'));

    expect($product->fresh()->name)->toBe('Nombre Nuevo')
        ->and($product->fresh()->status)->toBe('inactive');
});

test('a super admin can delete a product', function () {
    $product = Product::factory()->create();

    $this->delete(route('admin.products.destroy', $product))->assertRedirect();
    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});

test('search filters products by name or sku', function () {
    Product::factory()->create(['name' => 'Zapato Rojo', 'sku' => 'ZAP-1']);
    Product::factory()->create(['name' => 'Camisa Azul', 'sku' => 'CAM-1']);

    $this->get(route('admin.products.index', ['search' => 'Zapato']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products.data', 1));
});

test('a user without catalog permission is forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.products.index'))->assertForbidden();
});
