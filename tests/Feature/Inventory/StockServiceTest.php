<?php

use App\Domain\Inventory\StockAvailabilityChecker;
use App\Domain\Inventory\StockService;
use App\Models\InventorySource;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\StockMovement;

beforeEach(function () {
    $this->service = app(StockService::class);
    $this->checker = app(StockAvailabilityChecker::class);
    $this->source = InventorySource::factory()->default()->create();
    $this->product = Product::factory()->create();
});

test('available equals physical minus reserved', function () {
    $stock = InventoryStock::factory()->create([
        'product_id' => $this->product->id,
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 30,
        'reserved_qty' => 12,
    ]);

    expect($stock->available_qty)->toBe(18);
});

test('adjusting stock records a movement with the resulting balance', function () {
    $this->service->adjust($this->product, 25, StockMovement::TYPE_IN, $this->source, 'Recepción');

    $stock = $this->product->inventoryStocks()->first();
    expect($stock->physical_qty)->toBe(25);

    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $this->product->id,
        'type' => 'in',
        'quantity' => 25,
        'balance_after' => 25,
        'reason' => 'Recepción',
    ]);
});

test('setting physical stock computes the delta as an adjustment', function () {
    $this->service->adjust($this->product, 40, StockMovement::TYPE_IN, $this->source);
    $this->service->setPhysical($this->product, 15, $this->source, 'Conteo físico');

    $stock = $this->product->inventoryStocks()->first();
    expect($stock->physical_qty)->toBe(15);

    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $this->product->id,
        'type' => 'adjustment',
        'quantity' => -25,
        'balance_after' => 15,
    ]);
});

test('availability respects manage_stock disabled', function () {
    InventoryStock::factory()->create([
        'product_id' => $this->product->id,
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 0,
        'manage_stock' => false,
    ]);

    expect($this->checker->isAvailable($this->product, 999, $this->source))->toBeTrue();
});

test('availability respects backorders', function () {
    InventoryStock::factory()->create([
        'product_id' => $this->product->id,
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 1,
        'allow_backorders' => true,
    ]);

    expect($this->checker->isAvailable($this->product, 50, $this->source))->toBeTrue();
});

test('availability check by sku works', function () {
    InventoryStock::factory()->create([
        'product_id' => $this->product->id,
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 5,
    ]);

    expect($this->checker->isAvailableBySku($this->product->sku, 5, $this->source))->toBeTrue()
        ->and($this->checker->isAvailableBySku($this->product->sku, 6, $this->source))->toBeFalse();
});

test('low stock is detected against the threshold', function () {
    $stock = InventoryStock::factory()->create([
        'product_id' => $this->product->id,
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 3,
        'reserved_qty' => 0,
        'low_stock_threshold' => 5,
    ]);

    expect($stock->isLowStock())->toBeTrue();
});
