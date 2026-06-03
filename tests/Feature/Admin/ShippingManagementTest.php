<?php

use App\Models\ShippingMethod;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);

    $this->store = Store::factory()->create(['website_id' => Website::factory()]);
});

test('a super admin can list shipping methods', function () {
    $this->get(route('admin.shipping.index'))->assertOk();
});

test('a super admin can create a shipping method', function () {
    $this->post(route('admin.shipping.store'), [
        'code' => 'flat_rate',
        'name' => 'Envío estándar',
        'type' => 'flat_rate',
        'is_active' => '1',
    ])->assertRedirect(route('admin.shipping.index'));

    $this->assertDatabaseHas('shipping_methods', ['code' => 'flat_rate', 'type' => 'flat_rate']);
});

test('the shipping code must be unique', function () {
    ShippingMethod::factory()->create(['code' => 'dup']);

    $this->post(route('admin.shipping.store'), [
        'code' => 'dup',
        'name' => 'Otro',
        'type' => 'flat_rate',
    ])->assertSessionHasErrors('code');
});

test('the type must be valid', function () {
    $this->post(route('admin.shipping.store'), [
        'code' => 'bad',
        'name' => 'X',
        'type' => 'drone',
    ])->assertSessionHasErrors('type');
});

test('a super admin can delete a shipping method', function () {
    $method = ShippingMethod::factory()->create();

    $this->delete(route('admin.shipping.destroy', $method))->assertRedirect();
    $this->assertDatabaseMissing('shipping_methods', ['id' => $method->id]);
});

test('the per-store config page loads', function () {
    ShippingMethod::factory()->create();

    $this->get(route('admin.shipping-stores.edit', ['store_id' => $this->store->id]))->assertOk();
});

test('enabling a method for a store persists config and a base rate', function () {
    $method = ShippingMethod::factory()->create(['type' => 'flat_rate']);

    $this->put(route('admin.shipping-stores.update'), [
        'store_id' => $this->store->id,
        'methods' => [
            ['shipping_method_id' => $method->id, 'enabled' => '1', 'amount' => '89', 'free_over' => '999'],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('store_shipping_methods', [
        'store_id' => $this->store->id,
        'shipping_method_id' => $method->id,
        'is_active' => true,
        'free_over' => '999.00',
    ]);

    $ssm = StoreShippingMethod::where('store_id', $this->store->id)->firstOrFail();
    $this->assertDatabaseHas('shipping_rates', ['store_shipping_method_id' => $ssm->id, 'amount' => '89.00']);
});

test('disabling a method removes its store configuration', function () {
    $method = ShippingMethod::factory()->create();
    StoreShippingMethod::factory()->create(['store_id' => $this->store->id, 'shipping_method_id' => $method->id]);

    $this->put(route('admin.shipping-stores.update'), [
        'store_id' => $this->store->id,
        'methods' => [
            ['shipping_method_id' => $method->id, 'enabled' => '0'],
        ],
    ])->assertRedirect();

    $this->assertDatabaseMissing('store_shipping_methods', [
        'store_id' => $this->store->id,
        'shipping_method_id' => $method->id,
    ]);
});

test('a user without shipping permission is forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.shipping.index'))->assertForbidden();
});
