<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
    $this->actingAs($this->admin);
});

test('a super admin can create a dynamic bundle with components', function () {
    $a = Product::factory()->create(['type' => 'simple']);
    $b = Product::factory()->create(['type' => 'simple']);

    $this->post(route('admin.products.store'), [
        'type' => 'bundle',
        'price_type' => 'dynamic',
        'sku' => 'BUNDLE-1',
        'name' => 'Paquete Inicial',
        'status' => 'active',
        'visibility' => 'both',
        'bundle_items' => [
            ['product_id' => $a->id, 'quantity' => 1],
            ['product_id' => $b->id, 'quantity' => 2],
        ],
    ])->assertRedirect(route('admin.products.index'));

    $bundle = Product::where('sku', 'BUNDLE-1')->firstOrFail();

    expect($bundle->type)->toBe('bundle');
    expect($bundle->price_type)->toBe('dynamic');
    $this->assertDatabaseHas('bundle_items', ['bundle_product_id' => $bundle->id, 'product_id' => $a->id, 'quantity' => 1]);
    $this->assertDatabaseHas('bundle_items', ['bundle_product_id' => $bundle->id, 'product_id' => $b->id, 'quantity' => 2]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'product.created']);
});

test('a bundle requires at least one component', function () {
    $this->post(route('admin.products.store'), [
        'type' => 'bundle',
        'price_type' => 'dynamic',
        'sku' => 'BUNDLE-EMPTY',
        'name' => 'Paquete Vacío',
        'status' => 'active',
        'visibility' => 'both',
    ])->assertSessionHasErrors('bundle_items');
});

test('a fixed bundle keeps its own base price', function () {
    $a = Product::factory()->create(['type' => 'simple']);

    $this->post(route('admin.products.store'), [
        'type' => 'bundle',
        'price_type' => 'fixed',
        'sku' => 'BUNDLE-FIXED',
        'name' => 'Paquete Fijo',
        'status' => 'active',
        'visibility' => 'both',
        'price' => '499.00',
        'bundle_items' => [
            ['product_id' => $a->id, 'quantity' => 1],
        ],
    ])->assertRedirect();

    $bundle = Product::where('sku', 'BUNDLE-FIXED')->firstOrFail();
    $this->assertDatabaseHas('product_prices', ['product_id' => $bundle->id, 'store_id' => null, 'price' => '499.00']);
});

test('updating a bundle replaces its components', function () {
    $a = Product::factory()->create(['type' => 'simple']);
    $b = Product::factory()->create(['type' => 'simple']);
    $c = Product::factory()->create(['type' => 'simple']);

    $bundle = Product::factory()->create(['type' => 'bundle', 'price_type' => 'dynamic']);
    $bundle->bundleItems()->create(['product_id' => $a->id, 'quantity' => 1]);
    $bundle->bundleItems()->create(['product_id' => $b->id, 'quantity' => 1]);

    $this->put(route('admin.products.update', $bundle), [
        'sku' => $bundle->sku,
        'name' => $bundle->name,
        'status' => 'active',
        'visibility' => 'both',
        'price_type' => 'dynamic',
        'bundle_items' => [
            ['product_id' => $c->id, 'quantity' => 3],
        ],
    ])->assertRedirect();

    expect($bundle->bundleItems()->count())->toBe(1);
    $this->assertDatabaseHas('bundle_items', ['bundle_product_id' => $bundle->id, 'product_id' => $c->id, 'quantity' => 3]);
    $this->assertDatabaseMissing('bundle_items', ['bundle_product_id' => $bundle->id, 'product_id' => $a->id]);
});

test('the Soporte role cannot create products', function () {
    $support = User::factory()->create();
    $support->assignRole('Soporte');

    $this->actingAs($support)
        ->post(route('admin.products.store'), [
            'type' => 'bundle',
            'sku' => 'BUNDLE-NO',
            'name' => 'No permitido',
            'status' => 'active',
            'visibility' => 'both',
            'bundle_items' => [],
        ])->assertForbidden();
});
