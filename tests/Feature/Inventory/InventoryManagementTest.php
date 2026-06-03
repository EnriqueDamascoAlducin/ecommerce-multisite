<?php

use App\Models\InventorySource;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);

    $this->source = InventorySource::factory()->default()->create();
});

test('a super admin can list inventory', function () {
    Product::factory()->create();

    $this->get(route('admin.inventory.index'))->assertOk();
});

test('a super admin can adjust product stock', function () {
    $product = Product::factory()->create();

    $this->put(route('admin.inventory.update', $product), [
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 50,
        'manage_stock' => '1',
        'low_stock_threshold' => 5,
        'reason' => 'Carga inicial',
    ])->assertRedirect(route('admin.inventory.edit', $product));

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id,
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 50,
    ]);

    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $product->id,
        'type' => 'adjustment',
        'balance_after' => 50,
    ]);
});

test('the inventory edit page loads with sources and movements', function () {
    $product = Product::factory()->create();

    $this->get(route('admin.inventory.edit', $product))->assertOk();
});

test('a super admin can create an inventory source', function () {
    $this->post(route('admin.inventory-sources.store'), [
        'code' => 'bodega_norte',
        'name' => 'Bodega Norte',
        'is_active' => '1',
    ])->assertRedirect(route('admin.inventory-sources.index'));

    $this->assertDatabaseHas('inventory_sources', ['code' => 'bodega_norte']);
});

test('creating a default source unsets other defaults', function () {
    $this->post(route('admin.inventory-sources.store'), [
        'code' => 'nuevo_default',
        'name' => 'Nuevo Default',
        'is_default' => '1',
    ])->assertRedirect();

    expect(InventorySource::where('is_default', true)->count())->toBe(1);
    expect(InventorySource::where('code', 'nuevo_default')->value('is_default'))->toBeTrue();
});

test('the default source cannot be deleted', function () {
    $this->delete(route('admin.inventory-sources.destroy', $this->source))
        ->assertRedirect();

    $this->assertDatabaseHas('inventory_sources', ['id' => $this->source->id]);
});

test('a user without inventory permission is forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.inventory.index'))->assertForbidden();
});

test('a viewer cannot adjust stock', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Solo lectura');
    $this->actingAs($viewer);

    $product = Product::factory()->create();

    $this->put(route('admin.inventory.update', $product), [
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 10,
    ])->assertForbidden();
});
