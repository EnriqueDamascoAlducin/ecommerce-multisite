<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\StorefrontPage;
use App\Models\Website;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true]);

    $this->main = Store::factory()->create([
        'website_id' => $this->website->id,
        'code' => 'main',
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->sports = Store::factory()->create([
        'website_id' => $this->website->id,
        'code' => 'sports',
        'is_default' => false,
        'is_active' => true,
    ]);
});

/**
 * Crea un producto activo, con precio base, vinculado a una tienda concreta.
 */
function storeProduct(Store $store, string $name): Product
{
    $product = Product::factory()->create([
        'name' => $name,
        'status' => Product::STATUS_ACTIVE,
        'visibility' => 'both',
    ]);

    $product->prices()->create(['store_id' => null, 'price' => 100]);
    $product->storeLinks()->create(['store_id' => $store->id, 'is_active' => true]);

    return $product;
}

test('the store home is reachable at its bare path prefix', function () {
    $this->get('/sports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('storefront/home'));
});

test('each store at root and prefix shows only its own catalog', function () {
    storeProduct($this->main, 'Solo Main');
    storeProduct($this->sports, 'Solo Sports');

    // Raíz: host desconocido (localhost) cae al website por defecto -> tienda main.
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/home')
            ->has('featured', 1)
            ->where('featured.0.name', 'Solo Main'));

    // Prefijo /sports: tienda sports, catálogo aislado.
    $this->get('/sports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/home')
            ->has('featured', 1)
            ->where('featured.0.name', 'Solo Sports'));
});

test('categories are isolated per store', function () {
    $mainCat = Category::factory()->create([
        'store_id' => $this->main->id,
        'slug' => 'solo-main',
        'is_active' => true,
    ]);
    $sportsCat = Category::factory()->create([
        'store_id' => $this->sports->id,
        'slug' => 'solo-sports',
        'is_active' => true,
    ]);

    storeProduct($this->main, 'P Main')->categories()->attach($mainCat->id);
    storeProduct($this->sports, 'P Sports')->categories()->attach($sportsCat->id);

    $this->get('/sports/c/solo-sports');
    dump([
        'main_id' => $this->main->id,
        'sports_id' => $this->sports->id,
        'ctx_store_after_http' => app(\App\Domain\Store\StoreContext::class)->store()?->id,
        'ctx_prefix' => app(\App\Domain\Store\StoreContext::class)->pathPrefix(),
        'all_stores' => Store::all(['id', 'code', 'website_id', 'is_active'])->toArray(),
        'default_websites' => Website::where('is_default', true)->pluck('id')->all(),
        'direct_query' => Category::query()->where('store_id', 2)->where('slug', 'solo-sports')->where('is_active', true)->first()?->id,
        'sports_cat_raw' => Category::find($sportsCat->id)?->only(['id', 'slug', 'store_id', 'is_active']),
    ]);

    // Tienda sports (prefijo): ve la suya, no la de main.
    $this->get('/sports/c/solo-sports')->assertOk();
    $this->get('/sports/c/solo-main')->assertNotFound();

    // Tienda main (raíz): ve su categoría, no la de sports.
    $this->get('/c/solo-main')->assertOk();
    $this->get('/c/solo-sports')->assertNotFound();
});

test('a real cms page at root is not shadowed by the store-home fix', function () {
    StorefrontPage::factory()->create([
        'store_id' => $this->main->id,
        'slug' => 'nosotros',
        'is_published' => true,
    ]);

    // "nosotros" no es code de tienda -> debe resolver la página CMS, no la home.
    $this->get('/nosotros')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/home')
            ->where('contentPage.slug', 'nosotros'));
});
