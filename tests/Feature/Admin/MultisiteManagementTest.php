<?php

use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);
});

test('a super admin can list websites', function () {
    $this->get(route('admin.websites.index'))->assertOk();
});

test('a super admin can create a website', function () {
    $this->post(route('admin.websites.store'), [
        'code' => 'interferenciales',
        'name' => 'Interferenciales',
        'is_default' => true,
        'sort_order' => 1,
    ])->assertRedirect(route('admin.websites.index'));

    $this->assertDatabaseHas('websites', ['code' => 'interferenciales', 'is_default' => true]);
});

test('the default website cannot be deleted', function () {
    $website = Website::factory()->create(['is_default' => true]);

    $this->delete(route('admin.websites.destroy', $website));
    $this->assertDatabaseHas('websites', ['id' => $website->id]);
});

test('a super admin can create a store with domains', function () {
    $website = Website::factory()->create();

    $this->post(route('admin.stores.store'), [
        'website_id' => $website->id,
        'code' => 'main',
        'name' => 'Principal',
        'is_default' => true,
        'is_active' => true,
        'domains' => ['interferenciales.com.mx', 'www.interferenciales.com.mx'],
    ])->assertRedirect(route('admin.stores.index'));

    $store = Store::where('code', 'main')->firstOrFail();
    expect($store->domains)->toHaveCount(2);
    expect($store->domains()->where('is_primary', true)->first()->host)->toBe('interferenciales.com.mx');
});

test('a domain already used by another store is rejected', function () {
    $other = Store::factory()->create();
    StoreDomain::factory()->for($other)->create(['host' => 'taken.com.mx']);

    $website = Website::factory()->create();

    $this->post(route('admin.stores.store'), [
        'website_id' => $website->id,
        'code' => 'main',
        'name' => 'Principal',
        'domains' => ['taken.com.mx'],
    ])->assertSessionHasErrors('domains');
});

test('setting a store as default unsets the previous default in the same website', function () {
    $website = Website::factory()->create();
    $first = Store::factory()->for($website)->create(['is_default' => true, 'code' => 'a']);

    $this->post(route('admin.stores.store'), [
        'website_id' => $website->id,
        'code' => 'b',
        'name' => 'B',
        'is_default' => true,
    ])->assertRedirect();

    expect($first->fresh()->is_default)->toBeFalse();
});

test('switching scope and saving configuration persists it to that scope', function () {
    $website = Website::factory()->create();

    $this->post(route('admin.scope.update'), ['type' => 'website', 'id' => $website->id])
        ->assertRedirect();

    $this->put(route('admin.configuration.update'), ['values' => ['currency' => 'USD']])
        ->assertRedirect();

    $this->assertDatabaseHas('store_configurations', [
        'scope' => 'website',
        'scope_id' => $website->id,
        'key' => 'currency',
        'value' => 'USD',
    ]);
});

test('a user without store settings permission is forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.websites.index'))->assertForbidden();
});
