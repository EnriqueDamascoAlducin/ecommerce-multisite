<?php

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
    $this->source = InventorySource::factory()->create();
});

/**
 * Configurable activo en la tienda con una variante simple y stock dado.
 */
function configurableWithVariant(Store $store, InventorySource $source, int $physical, int $reserved = 0): Product
{
    $parent = Product::factory()->create([
        'type' => Product::TYPE_CONFIGURABLE,
        'status' => Product::STATUS_ACTIVE,
        'visibility' => 'both',
    ]);
    $parent->prices()->create(['store_id' => null, 'price' => 100]);
    $parent->storeLinks()->create(['store_id' => $store->id, 'is_active' => true]);

    $variant = Product::factory()->create([
        'type' => Product::TYPE_SIMPLE,
        'parent_id' => $parent->id,
        'status' => Product::STATUS_ACTIVE,
        'visibility' => 'both',
    ]);
    $variant->prices()->create(['store_id' => null, 'price' => 100]);
    $variant->storeLinks()->create(['store_id' => $store->id, 'is_active' => true]);
    $variant->inventoryStocks()->create([
        'inventory_source_id' => $source->id,
        'physical_qty' => $physical,
        'reserved_qty' => $reserved,
    ]);

    return $parent;
}

test('a configurable product page loads and reports in stock when a variant has availability', function () {
    $parent = configurableWithVariant($this->store, $this->source, physical: 5);

    $this->get(route('storefront.product', $parent->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/product')
            ->where('product.in_stock', true));
});

test('a configurable product reports out of stock when every variant is depleted', function () {
    $parent = configurableWithVariant($this->store, $this->source, physical: 3, reserved: 3);

    $this->get(route('storefront.product', $parent->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/product')
            ->where('product.in_stock', false));
});
