<?php

use App\Domain\Catalog\ConfigurableProductService;
use App\Models\Attribute;
use App\Models\Media;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CatalogSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);

    $this->website = Website::factory()->create();
    $this->store = Store::factory()->create(['website_id' => $this->website->id]);
    $this->color = Attribute::where('code', 'color')->firstOrFail();
});

/**
 * Configurable con un atributo configurable y vinculado a la tienda del sitio,
 * sin generar variantes (se controlan a mano en cada prueba).
 */
function bareConfigurable(Store $store, Attribute $attribute): Product
{
    $parent = Product::factory()->create([
        'type' => Product::TYPE_CONFIGURABLE,
        'status' => Product::STATUS_ACTIVE,
        'visibility' => 'both',
    ]);
    $parent->storeLinks()->create(['store_id' => $store->id, 'is_active' => true]);
    $parent->configurableAttributes()->sync([$attribute->id]);

    return $parent;
}

/**
 * Producto simple (candidato a variante) con un valor de atributo y tienda.
 */
function simpleCandidate(Store $store, Attribute $attribute, string $value): Product
{
    $product = Product::factory()->create([
        'type' => Product::TYPE_SIMPLE,
        'status' => Product::STATUS_ACTIVE,
        'visibility' => 'both',
    ]);
    $product->storeLinks()->create(['store_id' => $store->id, 'is_active' => true]);
    $product->attributeValues()->create(['attribute_id' => $attribute->id, 'value' => $value]);

    return $product;
}

test('inline edits update a variant price, sku, status, stock and image', function () {
    $this->post(route('admin.products.store'), [
        'type' => 'configurable',
        'sku' => 'CONF-INLINE',
        'name' => 'Inline',
        'status' => 'active',
        'visibility' => 'both',
        'price' => '100',
        'stores' => [['store_id' => $this->store->id, 'is_active' => '1']],
        'configurable_attributes' => [$this->color->id],
    ])->assertRedirect();

    $parent = Product::where('sku', 'CONF-INLINE')->firstOrFail();
    $variant = $parent->children()->firstOrFail();
    $media = Media::factory()->create(['is_image' => true]);

    $this->put(route('admin.products.update', $parent), [
        'sku' => 'CONF-INLINE',
        'name' => 'Inline',
        'status' => 'active',
        'visibility' => 'both',
        'price' => '100',
        'configurable_attributes' => [$this->color->id],
        'variants' => [[
            'id' => $variant->id,
            'sku' => 'VAR-CUSTOM-1',
            'price' => '49.99',
            'status' => 'inactive',
            'stock_qty' => 7,
            'media_id' => $media->id,
        ]],
    ])->assertRedirect();

    $variant->refresh();
    expect($variant->sku)->toBe('VAR-CUSTOM-1')
        ->and($variant->status)->toBe('inactive')
        ->and((float) $variant->prices()->whereNull('store_id')->value('price'))->toBe(49.99)
        ->and((int) $variant->inventoryStocks()->sum('physical_qty'))->toBe(7);

    $this->assertDatabaseHas('mediables', [
        'media_id' => $media->id,
        'mediable_id' => $variant->id,
        'collection' => 'gallery',
    ]);
});

test('an eligible simple product can be attached as a variant keeping its own data', function () {
    $parent = bareConfigurable($this->store, $this->color);
    $candidate = simpleCandidate($this->store, $this->color, 'rojo');
    $originalSku = $candidate->sku;

    $this->post(route('admin.products.variants.attach', $parent), [
        'product_id' => $candidate->id,
    ])->assertRedirect(route('admin.products.edit', $parent));

    $candidate->refresh();
    expect($candidate->parent_id)->toBe($parent->id)
        ->and($candidate->attributes['_variant_key'])->toBe('color:rojo')
        ->and($candidate->sku)->toBe($originalSku);

    $this->assertDatabaseHas('audit_logs', ['action' => 'product.variant.attached']);
});

test('attaching rejects a product from another website', function () {
    $parent = bareConfigurable($this->store, $this->color);

    $otherStore = Store::factory()->create(['website_id' => Website::factory()->create()->id]);
    $candidate = simpleCandidate($otherStore, $this->color, 'rojo');

    $this->post(route('admin.products.variants.attach', $parent), [
        'product_id' => $candidate->id,
    ])->assertSessionHasErrors('product_id');

    expect($candidate->fresh()->parent_id)->toBeNull();
});

test('attaching rejects a product missing a configurable attribute value', function () {
    $parent = bareConfigurable($this->store, $this->color);

    $candidate = Product::factory()->create(['type' => Product::TYPE_SIMPLE]);
    $candidate->storeLinks()->create(['store_id' => $this->store->id, 'is_active' => true]);

    $this->post(route('admin.products.variants.attach', $parent), [
        'product_id' => $candidate->id,
    ])->assertSessionHasErrors('product_id');
});

test('attaching rejects a duplicate option combination', function () {
    $parent = bareConfigurable($this->store, $this->color);

    // Variante existente con la combinación color:rojo.
    $existing = simpleCandidate($this->store, $this->color, 'rojo');
    app(ConfigurableProductService::class)->attachExistingVariant($parent, $existing);

    $duplicate = simpleCandidate($this->store, $this->color, 'rojo');

    $this->post(route('admin.products.variants.attach', $parent), [
        'product_id' => $duplicate->id,
    ])->assertSessionHasErrors('product_id');
});

test('a variant can be detached and becomes a standalone simple product', function () {
    $parent = bareConfigurable($this->store, $this->color);
    $candidate = simpleCandidate($this->store, $this->color, 'rojo');

    app(ConfigurableProductService::class)->attachExistingVariant($parent, $candidate);
    expect($candidate->fresh()->parent_id)->toBe($parent->id);

    $this->delete(route('admin.products.variants.detach', ['product' => $parent->id, 'variant' => $candidate->id]))
        ->assertRedirect(route('admin.products.edit', $parent));

    $candidate->refresh();
    expect($candidate->parent_id)->toBeNull()
        ->and($candidate->type)->toBe(Product::TYPE_SIMPLE)
        ->and($candidate->attributes['_variant_key'] ?? null)->toBeNull();
});

test('the grid hides variants by default and includes them on request', function () {
    $parent = bareConfigurable($this->store, $this->color);
    $variant = simpleCandidate($this->store, $this->color, 'rojo');
    $variant->update(['parent_id' => $parent->id, 'sku' => 'VAR-GRID-1']);

    $this->get(route('admin.products.index', ['search' => 'VAR-GRID-1']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products.data', 0));

    $this->get(route('admin.products.index', ['search' => 'VAR-GRID-1', 'variants' => 'include']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.parent.id', $parent->id));
});
