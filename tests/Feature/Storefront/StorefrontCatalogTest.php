<?php

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\HeaderMenuItem;
use App\Models\InventorySource;
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

/**
 * Crea un producto activo y con precio base en la tienda actual.
 */
function publishedProduct(Store $store, array $attributes = [], float $basePrice = 100): Product
{
    $product = Product::factory()->create(array_merge([
        'status' => Product::STATUS_ACTIVE,
        'visibility' => 'both',
    ], $attributes));

    $product->prices()->create(['store_id' => null, 'price' => $basePrice]);
    $product->storeLinks()->create(['store_id' => $store->id, 'is_active' => true]);

    return $product;
}

test('the home shows products active in the store', function () {
    publishedProduct($this->store);
    publishedProduct($this->store);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('storefront/home')->has('featured', 2));
});

test('the home excludes products not enabled in the store', function () {
    publishedProduct($this->store);

    // Producto activo pero NO vinculado a la tienda.
    $orphan = Product::factory()->create();
    $orphan->prices()->create(['store_id' => null, 'price' => 50]);

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page->has('featured', 1));
});

test('the home shares header menu items for the resolved store', function () {
    HeaderMenuItem::factory()->link()->create([
        'store_id' => $this->store->id,
        'label' => 'Ofertas',
        'url' => '/ofertas',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/home')
            ->where('store.menu.0.label', 'Ofertas')
            ->where('store.menu.0.url', '/ofertas'));
});

test('the home shares product menu item urls for the resolved store', function () {
    $product = publishedProduct($this->store);

    HeaderMenuItem::factory()->create([
        'store_id' => $this->store->id,
        'type' => HeaderMenuItem::TYPE_PRODUCT,
        'label' => 'Producto estrella',
        'product_id' => $product->id,
        'url' => null,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/home')
            ->where('store.menu.0.label', 'Producto estrella')
            ->where('store.menu.0.url', "/p/{$product->slug}"));
});

test('a category lists its products', function () {
    $category = Category::factory()->create(['website_id' => $this->website->id, 'slug' => 'ofertas']);
    $product = publishedProduct($this->store);
    $product->categories()->attach($category->id);

    publishedProduct($this->store); // no está en la categoría

    $this->get(route('storefront.category', 'ofertas'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('storefront/category')->has('products.data', 1));
});

test('a category product card shows dynamic bundle price', function () {
    $source = InventorySource::factory()->default()->create();
    $category = Category::factory()->create(['website_id' => $this->website->id, 'slug' => 'kits']);
    $shirt = sellableProduct($this->store, $source, 120);
    $cap = sellableProduct($this->store, $source, 80);
    $bundle = bundleProduct($this->store, [[$shirt, 1], [$cap, 2]]);
    $bundle->update(['name' => 'Kit Deportivo']);
    $bundle->categories()->attach($category->id);

    $this->get(route('storefront.category', 'kits'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/category')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Kit Deportivo')
            ->where('products.data.0.price.effective_price', '280.00'));
});

test('a category exposes filterable attribute options', function () {
    $category = Category::factory()->create(['website_id' => $this->website->id, 'slug' => 'ofertas']);
    $attribute = Attribute::factory()->select()->create([
        'is_filterable' => true,
        'code' => 'color',
        'name' => 'Color',
    ]);
    AttributeOption::create(['attribute_id' => $attribute->id, 'label' => 'Rojo', 'value' => 'red']);

    $product = publishedProduct($this->store);
    $product->categories()->attach($category->id);

    $this->get(route('storefront.category', 'ofertas'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/category')
            ->where('filters.attrs', [])
            ->where('filterOptions.attributes.0.name', 'Color')
            ->where('filterOptions.attributes.0.options.0.label', 'Rojo'));
});

test('a category filters products by select attribute', function () {
    $category = Category::factory()->create(['website_id' => $this->website->id, 'slug' => 'ofertas']);
    $attribute = Attribute::factory()->select()->create([
        'is_filterable' => true,
        'code' => 'color',
        'name' => 'Color',
    ]);
    AttributeOption::create(['attribute_id' => $attribute->id, 'label' => 'Rojo', 'value' => 'red']);
    AttributeOption::create(['attribute_id' => $attribute->id, 'label' => 'Azul', 'value' => 'blue']);

    $red = publishedProduct($this->store, ['name' => 'Producto Rojo']);
    $red->categories()->attach($category->id);
    $red->attributeValues()->create(['attribute_id' => $attribute->id, 'value' => 'red']);

    $blue = publishedProduct($this->store, ['name' => 'Producto Azul']);
    $blue->categories()->attach($category->id);
    $blue->attributeValues()->create(['attribute_id' => $attribute->id, 'value' => 'blue']);

    $this->get(route('storefront.category', ['slug' => 'ofertas', 'attrs' => [$attribute->id => 'red']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/category')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Producto Rojo'));
});

test('a category filters products by multiselect attribute', function () {
    $category = Category::factory()->create(['website_id' => $this->website->id, 'slug' => 'ofertas']);
    $attribute = Attribute::factory()->multiselect()->create([
        'is_filterable' => true,
        'code' => 'usos',
        'name' => 'Usos',
    ]);
    AttributeOption::create(['attribute_id' => $attribute->id, 'label' => 'Clínica', 'value' => 'clinic']);
    AttributeOption::create(['attribute_id' => $attribute->id, 'label' => 'Casa', 'value' => 'home']);

    $clinic = publishedProduct($this->store, ['name' => 'Equipo Clinica']);
    $clinic->categories()->attach($category->id);
    $clinic->attributeValues()->create(['attribute_id' => $attribute->id, 'value' => json_encode(['clinic'])]);

    $home = publishedProduct($this->store, ['name' => 'Equipo Casa']);
    $home->categories()->attach($category->id);
    $home->attributeValues()->create(['attribute_id' => $attribute->id, 'value' => json_encode(['home'])]);

    $this->get(route('storefront.category', ['slug' => 'ofertas', 'attrs' => [$attribute->id => ['clinic']]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/category')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Equipo Clinica'));
});

test('a category filters products by number range attribute', function () {
    $category = Category::factory()->create(['website_id' => $this->website->id, 'slug' => 'ofertas']);
    $attribute = Attribute::factory()->create([
        'type' => Attribute::TYPE_NUMBER,
        'is_filterable' => true,
        'code' => 'peso',
        'name' => 'Peso',
    ]);

    $light = publishedProduct($this->store, ['name' => 'Ligero']);
    $light->categories()->attach($category->id);
    $light->attributeValues()->create(['attribute_id' => $attribute->id, 'value' => '0.5']);

    $heavy = publishedProduct($this->store, ['name' => 'Pesado']);
    $heavy->categories()->attach($category->id);
    $heavy->attributeValues()->create(['attribute_id' => $attribute->id, 'value' => '5']);

    $this->get(route('storefront.category', ['slug' => 'ofertas', 'attrs' => [$attribute->id => ['min' => '1', 'max' => '10']]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/category')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Pesado'));
});

test('a category ignores non-filterable attributes', function () {
    $category = Category::factory()->create(['website_id' => $this->website->id, 'slug' => 'ofertas']);
    $attribute = Attribute::factory()->select()->create([
        'is_filterable' => false,
        'code' => 'color',
        'name' => 'Color',
    ]);

    $product = publishedProduct($this->store, ['name' => 'Producto']);
    $product->categories()->attach($category->id);
    $product->attributeValues()->create(['attribute_id' => $attribute->id, 'value' => 'red']);

    $this->get(route('storefront.category', ['slug' => 'ofertas', 'attrs' => [$attribute->id => 'red']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/category')
            ->has('products.data', 1)
            ->has('filterOptions.attributes', 0));
});

test('category pagination preserves active filters in links', function () {
    $category = Category::factory()->create(['website_id' => $this->website->id, 'slug' => 'ofertas']);
    $attribute = Attribute::factory()->select()->create([
        'is_filterable' => true,
        'code' => 'color',
        'name' => 'Color',
    ]);

    foreach (range(1, 13) as $index) {
        $product = publishedProduct($this->store, ['name' => "Producto {$index}"]);
        $product->categories()->attach($category->id);
        $product->attributeValues()->create(['attribute_id' => $attribute->id, 'value' => 'red']);
    }

    $this->get(route('storefront.category', ['slug' => 'ofertas', 'attrs' => [$attribute->id => 'red']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/category')
            ->has('products.data', 12)
            ->where('products.total', 13)
            ->where('filters.attrs.'.$attribute->id, 'red')
            ->where('products.next_page_url', fn ($url) => str_contains($url, 'attrs%5B'.$attribute->id.'%5D=red') || str_contains($url, 'attrs['.$attribute->id.']=red')));
});

test('an unknown category returns 404', function () {
    $this->get(route('storefront.category', 'no-existe'))->assertNotFound();
});

test('the product page shows the store-specific price', function () {
    $product = publishedProduct($this->store, [], 100);
    // Override de precio para la tienda.
    $product->prices()->create(['store_id' => $this->store->id, 'price' => 79.90]);

    $this->get(route('storefront.product', $product->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/product')
            ->where('product.price.effective_price', '79.90'));
});

test('the product page exposes ordered upsell and cross sell products', function () {
    $product = publishedProduct($this->store, ['name' => 'Base']);
    $upsellA = publishedProduct($this->store, ['name' => 'Upsell A'], 120);
    $upsellB = publishedProduct($this->store, ['name' => 'Upsell B'], 140);
    $crossSell = publishedProduct($this->store, ['name' => 'Cross Sell'], 30);
    $inactive = publishedProduct($this->store, [
        'name' => 'Inactive',
        'status' => Product::STATUS_INACTIVE,
    ]);
    $hidden = publishedProduct($this->store, ['name' => 'Hidden', 'visibility' => 'hidden']);
    $notInStore = Product::factory()->create([
        'name' => 'Other Store',
        'status' => Product::STATUS_ACTIVE,
        'visibility' => 'both',
    ]);
    $notInStore->prices()->create(['store_id' => null, 'price' => 90]);

    $product->upsellProducts()->attach($upsellB->id, [
        'type' => Product::LINK_TYPE_UPSELL,
        'sort_order' => 0,
    ]);
    $product->upsellProducts()->attach($upsellA->id, [
        'type' => Product::LINK_TYPE_UPSELL,
        'sort_order' => 1,
    ]);
    $product->upsellProducts()->attach($inactive->id, [
        'type' => Product::LINK_TYPE_UPSELL,
        'sort_order' => 2,
    ]);
    $product->upsellProducts()->attach($hidden->id, [
        'type' => Product::LINK_TYPE_UPSELL,
        'sort_order' => 3,
    ]);
    $product->upsellProducts()->attach($notInStore->id, [
        'type' => Product::LINK_TYPE_UPSELL,
        'sort_order' => 4,
    ]);
    $product->crossSellProducts()->attach($crossSell->id, [
        'type' => Product::LINK_TYPE_CROSS_SELL,
        'sort_order' => 0,
    ]);

    $this->get(route('storefront.product', $product->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/product')
            ->has('product.upsell_products', 2)
            ->where('product.upsell_products.0.name', 'Upsell B')
            ->where('product.upsell_products.1.name', 'Upsell A')
            ->where('product.upsell_products.0.price.effective_price', fn ($price) => (float) $price === 140.0)
            ->has('product.cross_sell_products', 1)
            ->where('product.cross_sell_products.0.name', 'Cross Sell'));
});

test('a product not active in the store returns 404', function () {
    $product = Product::factory()->create();
    $product->prices()->create(['store_id' => null, 'price' => 10]);
    // sin product_stores activo

    $this->get(route('storefront.product', $product->slug))->assertNotFound();
});

test('a hidden product is not reachable on the storefront', function () {
    $product = publishedProduct($this->store, ['visibility' => 'hidden']);

    $this->get(route('storefront.product', $product->slug))->assertNotFound();
});

test('an inactive product is not reachable', function () {
    $product = publishedProduct($this->store, ['status' => Product::STATUS_INACTIVE]);

    $this->get(route('storefront.product', $product->slug))->assertNotFound();
});
