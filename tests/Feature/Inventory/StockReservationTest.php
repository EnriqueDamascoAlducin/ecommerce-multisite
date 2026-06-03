<?php

use App\Domain\Inventory\InsufficientStockException;
use App\Domain\Inventory\StockReservationService;
use App\Models\InventorySource;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\StockReservation;

beforeEach(function () {
    $this->reservations = app(StockReservationService::class);
    $this->source = InventorySource::factory()->default()->create();
    $this->product = Product::factory()->create();

    $this->stock = InventoryStock::factory()->create([
        'product_id' => $this->product->id,
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 20,
        'reserved_qty' => 0,
    ]);
});

test('reserving stock increases reserved and lowers available', function () {
    $this->reservations->reserve($this->product, 5, 'cart:1', $this->source);

    $this->stock->refresh();
    expect($this->stock->reserved_qty)->toBe(5)
        ->and($this->stock->available_qty)->toBe(15)
        ->and($this->stock->physical_qty)->toBe(20);

    $this->assertDatabaseHas('stock_reservations', [
        'product_id' => $this->product->id,
        'quantity' => 5,
        'reference' => 'cart:1',
        'status' => 'active',
    ]);
});

test('reserving more than available throws', function () {
    $this->reservations->reserve($this->product, 999, 'cart:2', $this->source);
})->throws(InsufficientStockException::class);

test('releasing a reservation restores available stock', function () {
    $reservation = $this->reservations->reserve($this->product, 8, 'cart:3', $this->source);

    $this->reservations->release($reservation);

    $this->stock->refresh();
    expect($this->stock->reserved_qty)->toBe(0)
        ->and($this->stock->available_qty)->toBe(20);

    expect($reservation->fresh()->status)->toBe(StockReservation::STATUS_RELEASED);
});

test('consuming a reservation lowers both physical and reserved', function () {
    $reservation = $this->reservations->reserve($this->product, 6, 'order:9', $this->source);

    $this->reservations->consume($reservation);

    $this->stock->refresh();
    expect($this->stock->physical_qty)->toBe(14)
        ->and($this->stock->reserved_qty)->toBe(0)
        ->and($this->stock->available_qty)->toBe(14);

    expect($reservation->fresh()->status)->toBe(StockReservation::STATUS_CONSUMED);
});

test('reservations can be released by reference', function () {
    $this->reservations->reserve($this->product, 2, 'cart:42', $this->source);
    $this->reservations->reserve($this->product, 3, 'cart:42', $this->source);

    $released = $this->reservations->releaseByReference('cart:42');

    expect($released)->toBe(2);
    $this->stock->refresh();
    expect($this->stock->reserved_qty)->toBe(0);
});

test('backorders allow reserving beyond physical stock', function () {
    $this->stock->update(['allow_backorders' => true]);

    $this->reservations->reserve($this->product, 100, 'cart:5', $this->source);

    $this->stock->refresh();
    expect($this->stock->reserved_qty)->toBe(100)
        ->and($this->stock->available_qty)->toBe(-80);
});
