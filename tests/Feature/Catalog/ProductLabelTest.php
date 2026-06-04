<?php

use App\Models\InventorySource;
use App\Models\Product;
use App\Models\ProductLabel;
use App\Models\Store;
use App\Models\Website;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true]);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    $this->source = InventorySource::factory()->default()->create();
});

test('attaching a label to a product persists the pivot', function () {
    $product = Product::factory()->create();
    $label = ProductLabel::factory()->create(['website_id' => $this->website->id]);

    $product->labels()->attach($label->id);

    $this->assertDatabaseHas('product_product_label', [
        'product_id' => $product->id,
        'product_label_id' => $label->id,
    ]);
});

test('the PDP exposes only active labels of the current website', function () {
    $product = sellableProduct($this->store, $this->source);

    $active = ProductLabel::factory()->create(['website_id' => $this->website->id, 'text' => 'Oferta']);
    $inactive = ProductLabel::factory()->inactive()->create(['website_id' => $this->website->id, 'text' => 'Oculta']);
    $foreign = ProductLabel::factory()->create(['website_id' => Website::factory()->create()->id, 'text' => 'Ajena']);

    $product->labels()->attach([$active->id, $inactive->id, $foreign->id]);

    $this->get(route('storefront.product', $product->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/product')
            ->has('product.labels', 1)
            ->where('product.labels.0.text', 'Oferta'));
});

test('the catalog card exposes the product labels', function () {
    $product = sellableProduct($this->store, $this->source);
    $label = ProductLabel::factory()->create(['website_id' => $this->website->id, 'text' => 'Nuevo', 'background_color' => '#16a34a']);
    $product->labels()->attach($label->id);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('featured', 1)
            ->where('featured.0.labels.0.text', 'Nuevo')
            ->where('featured.0.labels.0.background_color', '#16a34a'));
});

test('labels are ordered by sort_order', function () {
    $product = sellableProduct($this->store, $this->source);
    $second = ProductLabel::factory()->create(['website_id' => $this->website->id, 'text' => 'B', 'sort_order' => 2]);
    $first = ProductLabel::factory()->create(['website_id' => $this->website->id, 'text' => 'A', 'sort_order' => 1]);

    $product->labels()->attach([$second->id, $first->id]);

    $this->get(route('storefront.product', $product->slug))
        ->assertInertia(fn ($page) => $page
            ->where('product.labels.0.text', 'A')
            ->where('product.labels.1.text', 'B'));
});
