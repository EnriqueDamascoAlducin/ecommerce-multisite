<?php

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Product;
use App\Models\Store;
use App\Models\Website;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true]);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
});

function searchPublishedProduct(Store $store, array $attributes = [], float $basePrice = 100): Product
{
    $product = Product::factory()->create(array_merge([
        'status' => Product::STATUS_ACTIVE,
        'visibility' => 'both',
    ], $attributes));

    $product->prices()->create(['store_id' => null, 'price' => $basePrice]);
    $product->storeLinks()->create(['store_id' => $store->id, 'is_active' => true]);

    return $product;
}

test('search finds products by name', function () {
    searchPublishedProduct($this->store, ['name' => 'Audifonos Bluetooth']);
    searchPublishedProduct($this->store, ['name' => 'Mouse Inalambrico']);

    $this->get(route('storefront.search', ['q' => 'Audifonos']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/search')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Audifonos Bluetooth'));
});

test('search finds products by sku', function () {
    searchPublishedProduct($this->store, ['sku' => 'AUD-001', 'name' => 'Audifonos']);
    searchPublishedProduct($this->store, ['sku' => 'MOU-001', 'name' => 'Mouse']);

    $this->get(route('storefront.search', ['q' => 'AUD-001']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/search')
            ->has('products.data', 1)
            ->where('products.data.0.sku', 'AUD-001'));
});

test('search without a term includes catalog products', function () {
    searchPublishedProduct($this->store, ['name' => 'Visible', 'visibility' => 'both']);
    searchPublishedProduct($this->store, ['name' => 'Solo catalogo', 'visibility' => 'catalog']);

    $this->get(route('storefront.search', ['q' => '']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/search')
            ->has('products.data', 2));
});

test('search without a term excludes search only and hidden products', function () {
    searchPublishedProduct($this->store, ['name' => 'Visible', 'visibility' => 'both']);
    searchPublishedProduct($this->store, ['name' => 'Solo busqueda', 'visibility' => 'search']);
    searchPublishedProduct($this->store, ['name' => 'Oculto', 'visibility' => 'hidden']);

    $this->get(route('storefront.search', ['q' => '']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/search')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Visible'));
});

test('search excludes inactive products', function () {
    searchPublishedProduct($this->store, ['name' => 'Activo']);
    searchPublishedProduct($this->store, ['name' => 'Inactivo', 'status' => Product::STATUS_INACTIVE]);

    $this->get(route('storefront.search', ['q' => '']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/search')
            ->has('products.data', 1));
});

test('search excludes products not linked to the store', function () {
    searchPublishedProduct($this->store, ['name' => 'En tienda']);

    $orphan = Product::factory()->create(['name' => 'Sin vinculo', 'status' => Product::STATUS_ACTIVE]);
    $orphan->prices()->create(['store_id' => null, 'price' => 50]);

    $this->get(route('storefront.search', ['q' => '']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/search')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'En tienda'));
});

test('search with a term includes products with visibility search', function () {
    searchPublishedProduct($this->store, ['name' => 'Visible en ambos', 'visibility' => 'both']);
    searchPublishedProduct($this->store, ['name' => 'Visible busqueda', 'visibility' => 'search']);

    $this->get(route('storefront.search', ['q' => 'Visible']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/search')
            ->has('products.data', 2)
            ->where('products.data.0.name', 'Visible en ambos'));
});

test('search filters by select attribute', function () {
    $attr = Attribute::factory()->select()->create([
        'is_filterable' => true,
        'code' => 'color',
        'name' => 'Color',
    ]);
    AttributeOption::factory()->create([
        'attribute_id' => $attr->id,
        'label' => 'Rojo',
        'value' => 'red',
    ]);
    AttributeOption::factory()->create([
        'attribute_id' => $attr->id,
        'label' => 'Azul',
        'value' => 'blue',
    ]);

    $rojo = searchPublishedProduct($this->store, ['name' => 'Producto Rojo']);
    $rojo->attributeValues()->create(['attribute_id' => $attr->id, 'value' => 'red']);

    $azul = searchPublishedProduct($this->store, ['name' => 'Producto Azul']);
    $azul->attributeValues()->create(['attribute_id' => $attr->id, 'value' => 'blue']);

    $this->get(route('storefront.search', ['q' => '', 'attrs' => [$attr->id => 'red']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/search')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Producto Rojo'));
});

test('search filters by boolean attribute', function () {
    $attr = Attribute::factory()->create([
        'type' => 'boolean',
        'is_filterable' => true,
        'code' => 'nuevo',
        'name' => 'Nuevo',
    ]);

    $nuevo = searchPublishedProduct($this->store, ['name' => 'Producto Nuevo']);
    $nuevo->attributeValues()->create(['attribute_id' => $attr->id, 'value' => '1']);

    $viejo = searchPublishedProduct($this->store, ['name' => 'Producto Viejo']);
    $viejo->attributeValues()->create(['attribute_id' => $attr->id, 'value' => '0']);

    $this->get(route('storefront.search', ['q' => '', 'attrs' => [$attr->id => '1']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/search')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Producto Nuevo'));
});

test('search filters by number range attribute', function () {
    $attr = Attribute::factory()->create([
        'type' => 'number',
        'is_filterable' => true,
        'code' => 'peso',
        'name' => 'Peso (kg)',
    ]);

    $ligero = searchPublishedProduct($this->store, ['name' => 'Ligero']);
    $ligero->attributeValues()->create(['attribute_id' => $attr->id, 'value' => '0.5']);

    $pesado = searchPublishedProduct($this->store, ['name' => 'Pesado']);
    $pesado->attributeValues()->create(['attribute_id' => $attr->id, 'value' => '5']);

    $this->get(route('storefront.search', ['q' => '', 'attrs' => [$attr->id => ['min' => '1', 'max' => '10']]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/search')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Pesado'));
});

test('search does not filter by non-filterable attributes', function () {
    $attr = Attribute::factory()->select()->create([
        'is_filterable' => false,
        'code' => 'color',
        'name' => 'Color',
    ]);

    $product = searchPublishedProduct($this->store, ['name' => 'Producto']);
    $product->attributeValues()->create(['attribute_id' => $attr->id, 'value' => 'red']);

    // El filtro se ignora porque el atributo no es filterable
    $this->get(route('storefront.search', ['q' => '', 'attrs' => [$attr->id => 'red']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/search')
            ->has('products.data', 1));
});
