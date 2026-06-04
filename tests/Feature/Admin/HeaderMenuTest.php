<?php

use App\Models\Category;
use App\Models\HeaderMenuItem;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);
});

test('the index page loads for a store', function () {
    $store = Store::factory()->create();

    $this->get(route('admin.header-menu.index', ['store_id' => $store->id]))
        ->assertOk();
});

test('a link item can be created', function () {
    $store = Store::factory()->create();

    $this->post(route('admin.header-menu.store'), [
        'store_id' => $store->id,
        'type' => 'link',
        'label' => 'Ofertas',
        'url' => '/ofertas',
        'is_active' => '1',
    ])->assertRedirect();

    $this->assertDatabaseHas('store_header_menu_items', [
        'store_id' => $store->id,
        'type' => 'link',
        'label' => 'Ofertas',
        'url' => '/ofertas',
        'parent_id' => null,
    ]);
});

test('a category item can be created', function () {
    $store = Store::factory()->create();
    $category = Category::factory()->create(['website_id' => $store->website_id]);

    $this->post(route('admin.header-menu.store'), [
        'store_id' => $store->id,
        'type' => 'category',
        'label' => 'Electrónica',
        'category_id' => $category->id,
        'is_active' => '1',
    ])->assertRedirect();

    $this->assertDatabaseHas('store_header_menu_items', [
        'store_id' => $store->id,
        'label' => 'Electrónica',
        'category_id' => $category->id,
    ]);
});

test('a child item can be nested under a parent', function () {
    $store = Store::factory()->create();
    $parent = HeaderMenuItem::factory()->create(['store_id' => $store->id]);

    $this->post(route('admin.header-menu.store'), [
        'store_id' => $store->id,
        'parent_id' => $parent->id,
        'type' => 'link',
        'label' => 'Subitem',
        'url' => '/sub',
    ])->assertRedirect();

    $this->assertDatabaseHas('store_header_menu_items', [
        'label' => 'Subitem',
        'parent_id' => $parent->id,
    ]);
});

test('an item can be updated', function () {
    $item = HeaderMenuItem::factory()->create();

    $this->put(route('admin.header-menu.update', $item), [
        'store_id' => $item->store_id,
        'type' => 'link',
        'label' => 'Actualizado',
        'url' => '/nuevo-url',
        'expand_products' => '1',
    ])->assertRedirect();

    $this->assertDatabaseHas('store_header_menu_items', [
        'id' => $item->id,
        'label' => 'Actualizado',
        'expand_products' => 1,
    ]);
});

test('an item can be deleted', function () {
    $item = HeaderMenuItem::factory()->create();

    $this->delete(route('admin.header-menu.destroy', $item))->assertRedirect();

    $this->assertDatabaseMissing('store_header_menu_items', ['id' => $item->id]);
});

test('items are scoped to the parent store', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    HeaderMenuItem::factory()->count(2)->create(['store_id' => $storeA->id]);
    HeaderMenuItem::factory()->count(3)->create(['store_id' => $storeB->id]);

    $response = $this->get(route('admin.header-menu.index', ['store_id' => $storeA->id]));
    $response->assertOk();

    $response->assertSee('store_id');
});

test('a user without settings.storefront permission is forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.header-menu.index'))->assertForbidden();
});
