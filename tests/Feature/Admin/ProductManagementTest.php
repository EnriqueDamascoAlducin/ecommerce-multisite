<?php

use App\Models\Attribute;
use App\Models\Category;
use App\Models\InventorySource;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductLabel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

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
        'type' => 'simple',
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
        'type' => 'simple',
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
        'type' => 'simple',
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
        'type' => 'simple',
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
        'type' => 'simple',
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

test('product grid filters by status type and visibility', function () {
    Product::factory()->create([
        'sku' => 'MATCH-001',
        'type' => Product::TYPE_BUNDLE,
        'status' => Product::STATUS_ACTIVE,
        'visibility' => 'catalog',
    ]);
    Product::factory()->create([
        'sku' => 'MISS-001',
        'type' => Product::TYPE_SIMPLE,
        'status' => Product::STATUS_INACTIVE,
        'visibility' => 'hidden',
    ]);

    $this->get(route('admin.products.index', [
        'status' => Product::STATUS_ACTIVE,
        'type' => Product::TYPE_BUNDLE,
        'visibility' => 'catalog',
    ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.sku', 'MATCH-001')
        );
});

test('product grid filters by category store label and stock', function () {
    $category = Category::factory()->create();
    $store = Store::factory()->create(['website_id' => $category->website_id]);
    $label = ProductLabel::factory()->create(['website_id' => $category->website_id]);
    $source = InventorySource::factory()->create();
    $matching = Product::factory()->create(['sku' => 'MATCH-REL']);
    $missing = Product::factory()->create(['sku' => 'MISS-REL']);

    $matching->categories()->attach($category->id);
    $matching->storeLinks()->create(['store_id' => $store->id, 'is_active' => true]);
    $matching->labels()->attach($label->id);
    $matching->inventoryStocks()->create([
        'inventory_source_id' => $source->id,
        'physical_qty' => 8,
        'reserved_qty' => 2,
    ]);
    $missing->inventoryStocks()->create([
        'inventory_source_id' => $source->id,
        'physical_qty' => 0,
        'reserved_qty' => 0,
    ]);

    $this->get(route('admin.products.index', [
        'category_id' => $category->id,
        'store_id' => $store->id,
        'label_id' => $label->id,
        'stock' => 'in',
    ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.sku', 'MATCH-REL')
            ->where('products.data.0.stock.available', 6)
        );
});

test('product grid filters by filterable attributes and exposes visible columns', function () {
    $color = Attribute::factory()->select()->create([
        'name' => 'Color',
        'is_filterable' => true,
        'is_visible' => true,
    ]);
    $color->options()->create(['label' => 'Rojo', 'value' => 'red', 'sort_order' => 0]);
    $watts = Attribute::factory()->create([
        'name' => 'Watts',
        'type' => Attribute::TYPE_NUMBER,
        'is_filterable' => true,
        'is_visible' => true,
    ]);
    $notes = Attribute::factory()->create([
        'name' => 'Notas',
        'type' => Attribute::TYPE_TEXT,
        'is_filterable' => true,
        'is_visible' => false,
    ]);
    $matching = Product::factory()->create(['sku' => 'MATCH-ATTR']);
    $missing = Product::factory()->create(['sku' => 'MISS-ATTR']);

    ProductAttributeValue::create(['product_id' => $matching->id, 'attribute_id' => $color->id, 'value' => 'red']);
    ProductAttributeValue::create(['product_id' => $matching->id, 'attribute_id' => $watts->id, 'value' => '120']);
    ProductAttributeValue::create(['product_id' => $matching->id, 'attribute_id' => $notes->id, 'value' => 'clinico premium']);
    ProductAttributeValue::create(['product_id' => $missing->id, 'attribute_id' => $color->id, 'value' => 'blue']);
    ProductAttributeValue::create(['product_id' => $missing->id, 'attribute_id' => $watts->id, 'value' => '40']);
    ProductAttributeValue::create(['product_id' => $missing->id, 'attribute_id' => $notes->id, 'value' => 'basico']);

    $this->get(route('admin.products.index', [
        'attrs' => [
            $color->id => 'red',
            $watts->id => ['min' => '100', 'max' => '140'],
            $notes->id => 'premium',
        ],
    ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.sku', 'MATCH-ATTR')
            ->where("products.data.0.attributes.{$color->id}.label", 'Rojo')
            ->where('filterOptions.attributes.0.name', 'Color')
            ->where('columns.11.label', 'Color')
        );
});

test('a product can store upsell and cross sell products in order', function () {
    $product = Product::factory()->create(['sku' => 'BASE-REL']);
    $upsellA = Product::factory()->create(['sku' => 'UP-A']);
    $upsellB = Product::factory()->create(['sku' => 'UP-B']);
    $crossSell = Product::factory()->create(['sku' => 'CS-A']);

    $this->put(route('admin.products.update', $product), [
        'sku' => $product->sku,
        'name' => $product->name,
        'status' => 'active',
        'visibility' => 'both',
        'price' => '50',
        'upsell_products' => [$upsellB->id, $upsellA->id],
        'cross_sell_products' => [$crossSell->id],
    ])->assertRedirect(route('admin.products.index'));

    $this->assertDatabaseHas('product_links', [
        'product_id' => $product->id,
        'linked_product_id' => $upsellB->id,
        'type' => Product::LINK_TYPE_UPSELL,
        'sort_order' => 0,
    ]);
    $this->assertDatabaseHas('product_links', [
        'product_id' => $product->id,
        'linked_product_id' => $upsellA->id,
        'type' => Product::LINK_TYPE_UPSELL,
        'sort_order' => 1,
    ]);
    $this->assertDatabaseHas('product_links', [
        'product_id' => $product->id,
        'linked_product_id' => $crossSell->id,
        'type' => Product::LINK_TYPE_CROSS_SELL,
        'sort_order' => 0,
    ]);
});

test('updating related products removes omitted links', function () {
    $product = Product::factory()->create();
    $keep = Product::factory()->create();
    $remove = Product::factory()->create();

    $product->upsellProducts()->attach($keep->id, [
        'type' => Product::LINK_TYPE_UPSELL,
        'sort_order' => 0,
    ]);
    $product->upsellProducts()->attach($remove->id, [
        'type' => Product::LINK_TYPE_UPSELL,
        'sort_order' => 1,
    ]);

    $this->put(route('admin.products.update', $product), [
        'sku' => $product->sku,
        'name' => $product->name,
        'status' => 'active',
        'visibility' => 'both',
        'price' => '50',
        'upsell_products' => [$keep->id],
    ])->assertRedirect();

    $this->assertDatabaseHas('product_links', [
        'product_id' => $product->id,
        'linked_product_id' => $keep->id,
        'type' => Product::LINK_TYPE_UPSELL,
    ]);
    $this->assertDatabaseMissing('product_links', [
        'product_id' => $product->id,
        'linked_product_id' => $remove->id,
        'type' => Product::LINK_TYPE_UPSELL,
    ]);
});

test('related product ids must be distinct', function () {
    $product = Product::factory()->create();
    $related = Product::factory()->create();

    $this->put(route('admin.products.update', $product), [
        'sku' => $product->sku,
        'name' => $product->name,
        'status' => 'active',
        'visibility' => 'both',
        'price' => '50',
        'upsell_products' => [$related->id, $related->id],
    ])->assertSessionHasErrors('upsell_products.1');
});

test('a product cannot be linked to itself', function () {
    $product = Product::factory()->create();

    $this->put(route('admin.products.update', $product), [
        'sku' => $product->sku,
        'name' => $product->name,
        'status' => 'active',
        'visibility' => 'both',
        'price' => '50',
        'cross_sell_products' => [$product->id],
    ])->assertSessionHasErrors('cross_sell_products');
});

test('a user without catalog permission is forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.products.index'))->assertForbidden();
});
