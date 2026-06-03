<?php

use App\Domain\Inventory\StockReservationService;
use App\Models\InventorySource;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\StockReservation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create()->assignRole('Super Admin');
    actingAs($this->admin);

    $this->product = Product::factory()->create(['type' => 'simple', 'status' => 'active']);
    $this->source = InventorySource::default() ?? InventorySource::factory()->default()->create();

    InventoryStock::factory()->create([
        'product_id' => $this->product->id,
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 50,
        'reserved_qty' => 5,
        'manage_stock' => true,
    ]);

    $this->order = Order::factory()->create(['status' => Order::STATUS_PAID]);

    OrderItem::factory()->create([
        'order_id' => $this->order->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-TEST',
        'name' => 'Producto test',
        'quantity' => 3,
        'unit_price' => 100,
        'line_total' => 300,
    ]);

    $this->order->load('items');

    $reservations = app(StockReservationService::class);
    $reservations->reserve($this->product, 3, "order:{$this->order->id}", $this->source);
});

function createShipment(): Shipment
{
    $orderItem = test()->order->items->first();
    $response = post(route('admin.shipments.store'), [
        'order_id' => test()->order->id,
        'items' => [['order_item_id' => $orderItem->id, 'quantity' => 3]],
    ]);
    $response->assertSessionDoesntHaveErrors();

    return Shipment::first();
}

it('generates a shipment from a paid order', function () {
    $orderItem = $this->order->items->first();

    $response = post(route('admin.shipments.store'), [
        'order_id' => $this->order->id,
        'items' => [['order_item_id' => $orderItem->id, 'quantity' => 3]],
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect(route('admin.orders.show', $this->order));

    $this->assertDatabaseHas('shipments', [
        'order_id' => $this->order->id,
        'status' => Shipment::STATUS_PENDING,
        'total_qty' => 3,
    ]);

    $this->assertDatabaseHas('shipment_items', [
        'order_item_id' => $orderItem->id,
        'quantity' => 3,
    ]);

    $this->order->refresh();
    expect($this->order->status)->toBe(Order::STATUS_SHIPPED);
});

it('prevents duplicate shipments for fully shipped order', function () {
    $orderItem = $this->order->items->first();

    post(route('admin.shipments.store'), [
        'order_id' => $this->order->id,
        'items' => [['order_item_id' => $orderItem->id, 'quantity' => 3]],
    ]);

    post(route('admin.shipments.store'), [
        'order_id' => $this->order->id,
        'items' => [['order_item_id' => $orderItem->id, 'quantity' => 3]],
    ])->assertSessionHas('error');
});

it('allows partial shipment', function () {
    $orderItem = $this->order->items->first();

    $response = post(route('admin.shipments.store'), [
        'order_id' => $this->order->id,
        'items' => [['order_item_id' => $orderItem->id, 'quantity' => 2]],
    ]);
    $response->assertSessionDoesntHaveErrors();

    $this->assertDatabaseHas('shipments', ['total_qty' => 2]);

    $this->order->refresh();
    expect($this->order->status)->toBe(Order::STATUS_PARTIALLY_SHIPPED);
});

it('consumes stock reservation on shipment', function () {
    $shipment = createShipment();

    $reservation = StockReservation::where('reference', "order:{$this->order->id}")->first();
    expect($reservation)->not->toBeNull();
    expect($reservation->status)->toBe(StockReservation::STATUS_CONSUMED);

    $stock = InventoryStock::where('product_id', $this->product->id)->first();
    expect($stock->physical_qty)->toBe(47);
    expect($stock->reserved_qty)->toBe(5);
});

it('marks shipment as shipped', function () {
    $shipment = createShipment();

    $response = post(route('admin.shipments.ship', $shipment), [
        'carrier_code' => 'correosmex',
        'carrier_label' => 'Correos de México',
        'tracking_number' => 'TRACK123',
    ]);
    $response->assertSessionHas('success');

    $shipment->refresh();
    expect($shipment->status)->toBe(Shipment::STATUS_SHIPPED);
    expect($shipment->carrier_code)->toBe('correosmex');
    expect($shipment->tracking_number)->toBe('TRACK123');
    expect($shipment->shipped_at)->not->toBeNull();
});

it('marks shipment as delivered and completes order', function () {
    $shipment = createShipment();

    post(route('admin.shipments.ship', $shipment), ['carrier_label' => 'Test']);
    post(route('admin.shipments.deliver', $shipment))->assertSessionHas('success');

    $shipment->refresh();
    expect($shipment->status)->toBe(Shipment::STATUS_DELIVERED);
    expect($shipment->delivered_at)->not->toBeNull();

    $this->order->refresh();
    expect($this->order->status)->toBe(Order::STATUS_COMPLETE);
});

it('cancels a pending shipment', function () {
    $shipment = createShipment();

    post(route('admin.shipments.cancel', $shipment))->assertSessionHas('success');

    $shipment->refresh();
    expect($shipment->status)->toBe(Shipment::STATUS_CANCELLED);
});

it('lists shipments with filters', function () {
    createShipment();

    get(route('admin.shipments.index'))->assertOk();
    get(route('admin.shipments.index', ['status' => 'pending']))->assertOk();
});

it('shows shipment detail', function () {
    $shipment = createShipment();

    get(route('admin.shipments.show', $shipment))->assertOk();
});

it('enforces shipment permissions', function () {
    $user = User::factory()->create()->assignRole('Solo lectura');
    actingAs($user);

    post(route('admin.shipments.store'), [
        'order_id' => $this->order->id,
        'items' => [['order_item_id' => $this->order->items->first()->id, 'quantity' => 3]],
    ])->assertForbidden();
});
