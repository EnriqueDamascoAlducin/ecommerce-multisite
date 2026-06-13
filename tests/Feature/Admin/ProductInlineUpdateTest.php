<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);
});

test('inline update changes status without touching the slug', function () {
    $product = Product::factory()->create([
        'status' => Product::STATUS_ACTIVE,
        'slug' => 'camiseta-roja',
    ]);

    $this->patch(route('admin.products.inline-update', $product), [
        'field' => 'status',
        'value' => Product::STATUS_INACTIVE,
    ])->assertRedirect();

    $product->refresh();
    expect($product->status)->toBe(Product::STATUS_INACTIVE);
    expect($product->slug)->toBe('camiseta-roja');
});

test('inline update changes visibility', function () {
    $product = Product::factory()->create(['visibility' => 'both']);

    $this->patch(route('admin.products.inline-update', $product), [
        'field' => 'visibility',
        'value' => 'catalog',
    ])->assertRedirect();

    expect($product->refresh()->visibility)->toBe('catalog');
});

test('inline update changes name keeping the slug stable', function () {
    $product = Product::factory()->create([
        'name' => 'Nombre Viejo',
        'slug' => 'nombre-viejo',
    ]);

    $this->patch(route('admin.products.inline-update', $product), [
        'field' => 'name',
        'value' => 'Nombre Nuevo',
    ])->assertRedirect();

    $product->refresh();
    expect($product->name)->toBe('Nombre Nuevo');
    expect($product->slug)->toBe('nombre-viejo');
});

test('inline update rejects an invalid value', function () {
    $product = Product::factory()->create(['status' => Product::STATUS_ACTIVE]);

    $this->patch(route('admin.products.inline-update', $product), [
        'field' => 'status',
        'value' => 'foo',
    ])->assertSessionHasErrors('value');

    expect($product->refresh()->status)->toBe(Product::STATUS_ACTIVE);
});

test('inline update rejects a field that is not editable inline', function () {
    $product = Product::factory()->create(['sku' => 'KEEP-SKU']);

    $this->patch(route('admin.products.inline-update', $product), [
        'field' => 'sku',
        'value' => 'HACKED',
    ])->assertSessionHasErrors('field');

    expect($product->refresh()->sku)->toBe('KEEP-SKU');
});

test('a user without edit permission cannot inline update', function () {
    $this->actingAs(User::factory()->create());

    $product = Product::factory()->create(['status' => Product::STATUS_ACTIVE]);

    $this->patch(route('admin.products.inline-update', $product), [
        'field' => 'status',
        'value' => Product::STATUS_INACTIVE,
    ])->assertForbidden();

    expect($product->refresh()->status)->toBe(Product::STATUS_ACTIVE);
});
