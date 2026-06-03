<?php

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);

    $this->website = Website::factory()->create();
});

test('a product can be assigned to categories', function () {
    $a = Category::factory()->create(['website_id' => $this->website->id]);
    $b = Category::factory()->create(['website_id' => $this->website->id]);

    $this->post(route('admin.products.store'), [
        'type' => 'simple',
        'sku' => 'SKU-CAT',
        'name' => 'Con categorías',
        'status' => 'active',
        'visibility' => 'both',
        'price' => '10',
        'categories' => [$a->id, $b->id],
    ])->assertRedirect();

    $product = Product::where('sku', 'SKU-CAT')->firstOrFail();
    expect($product->categories)->toHaveCount(2);
});

test('a product stores simple attribute values', function () {
    $material = Attribute::factory()->create(['code' => 'material', 'type' => 'text']);

    $this->post(route('admin.products.store'), [
        'type' => 'simple',
        'sku' => 'SKU-ATTR',
        'name' => 'Con atributo',
        'status' => 'active',
        'visibility' => 'both',
        'price' => '10',
        'attribute_values' => [$material->id => 'Algodón'],
    ])->assertRedirect();

    $product = Product::where('sku', 'SKU-ATTR')->firstOrFail();

    $this->assertDatabaseHas('product_attribute_values', [
        'product_id' => $product->id,
        'attribute_id' => $material->id,
        'value' => 'Algodón',
    ]);
});

test('a multiselect attribute value is stored as json', function () {
    $color = Attribute::factory()->multiselect()->create(['code' => 'color']);

    $this->post(route('admin.products.store'), [
        'type' => 'simple',
        'sku' => 'SKU-MULTI',
        'name' => 'Multi',
        'status' => 'active',
        'visibility' => 'both',
        'price' => '10',
        'attribute_values' => [$color->id => ['rojo', 'azul']],
    ])->assertRedirect();

    $product = Product::where('sku', 'SKU-MULTI')->firstOrFail();
    $value = $product->attributeValues()->where('attribute_id', $color->id)->value('value');

    expect(json_decode($value, true))->toBe(['rojo', 'azul']);
});

test('an empty attribute value removes the row on update', function () {
    $material = Attribute::factory()->create(['code' => 'material', 'type' => 'text']);
    $product = Product::factory()->create();
    $product->attributeValues()->create(['attribute_id' => $material->id, 'value' => 'Cuero']);

    $this->put(route('admin.products.update', $product), [
        'type' => 'simple',
        'sku' => $product->sku,
        'name' => $product->name,
        'status' => 'active',
        'visibility' => 'both',
        'price' => '10',
        'attribute_values' => [$material->id => ''],
    ])->assertRedirect();

    $this->assertDatabaseMissing('product_attribute_values', [
        'product_id' => $product->id,
        'attribute_id' => $material->id,
    ]);
});
