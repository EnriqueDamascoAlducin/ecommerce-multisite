<?php

use App\Domain\Store\HeaderMenuService;
use App\Models\Category;
use App\Models\HeaderMenuItem;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(HeaderMenuService::class);
});

test('buildTree returns an empty array when no items exist', function () {
    $store = Store::factory()->create();

    $tree = $this->service->buildTree($store);

    expect($tree)->toBe([]);
});

test('buildTree returns active root items ordered by sort_order', function () {
    $store = Store::factory()->create();

    HeaderMenuItem::factory()->link()->create([
        'store_id' => $store->id,
        'sort_order' => 1,
        'label' => 'Second',
    ]);
    HeaderMenuItem::factory()->link()->create([
        'store_id' => $store->id,
        'sort_order' => 0,
        'label' => 'First',
    ]);

    $tree = $this->service->buildTree($store);

    expect($tree)->toHaveCount(2);
    expect($tree[0]['label'])->toBe('First');
});

test('buildTree excludes inactive items', function () {
    $store = Store::factory()->create();
    HeaderMenuItem::factory()->active(false)->create(['store_id' => $store->id, 'label' => 'Hidden']);
    HeaderMenuItem::factory()->create(['store_id' => $store->id, 'label' => 'Visible']);

    $tree = $this->service->buildTree($store);

    expect($tree)->toHaveCount(1);
    expect($tree[0]['label'])->toBe('Visible');
});

test('buildTree nests children under their parent', function () {
    $store = Store::factory()->create();
    $parent = HeaderMenuItem::factory()->link()->create(['store_id' => $store->id, 'label' => 'Padre']);
    HeaderMenuItem::factory()->link()->create([
        'store_id' => $store->id,
        'parent_id' => $parent->id,
        'label' => 'Hijo',
    ]);

    $tree = $this->service->buildTree($store);

    expect($tree)->toHaveCount(1);
    expect($tree[0]['children'])->toHaveCount(1);
    expect($tree[0]['children'][0]['label'])->toBe('Hijo');
});

test('categoryProducts returns products for a category', function () {
    $store = Store::factory()->create();
    $category = Category::factory()->create(['website_id' => $store->website_id]);
    $product = Product::factory()->create();
    $product->categories()->attach($category->id);
    $product->storeLinks()->create(['store_id' => $store->id, 'is_active' => true]);

    $products = $this->service->categoryProducts($category->id, $store->id);

    expect($products)->toHaveCount(1);
    expect($products[0]['name'])->toBe($product->name);
});

test('buildTree loads products when expand_products is true', function () {
    $store = Store::factory()->create();
    $category = Category::factory()->create(['website_id' => $store->website_id]);
    $product = Product::factory()->create();
    $product->categories()->attach($category->id);
    $product->storeLinks()->create(['store_id' => $store->id, 'is_active' => true]);

    HeaderMenuItem::factory()->category()->expandProducts()->create([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'label' => 'Catálogo',
    ]);

    $tree = $this->service->buildTree($store);

    expect($tree)->toHaveCount(1);
    expect($tree[0]['expand_products'])->toBeTrue();
    expect($tree[0]['products'])->toHaveCount(1);
    expect($tree[0]['products'][0]['name'])->toBe($product->name);
});
