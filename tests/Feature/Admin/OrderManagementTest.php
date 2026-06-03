<?php

use App\Domain\Inventory\StockReservationService;
use App\Models\InventorySource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);
});

test('a super admin can list orders', function () {
    Order::factory()->create();

    $this->get(route('admin.orders.index'))->assertOk();
});

test('a super admin can view an order', function () {
    $order = Order::factory()->create();

    $this->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/orders/show')->where('order.number', $order->number));
});

test('updating the status records history', function () {
    $order = Order::factory()->create(['status' => 'pending_payment']);

    $this->put(route('admin.orders.status', $order), [
        'status' => 'processing',
        'comment' => 'Pago confirmado',
    ])->assertRedirect();

    expect($order->fresh()->status)->toBe('processing');
    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->id,
        'from_status' => 'pending_payment',
        'to_status' => 'processing',
        'comment' => 'Pago confirmado',
    ]);
});

test('an internal comment can be added without changing status', function () {
    $order = Order::factory()->create(['status' => 'processing']);

    $this->post(route('admin.orders.comment', $order), ['comment' => 'Cliente llamó'])->assertRedirect();

    expect($order->fresh()->status)->toBe('processing');
    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->id,
        'to_status' => 'processing',
        'comment' => 'Cliente llamó',
    ]);
});

test('cancelling an order releases its stock reservations', function () {
    $source = InventorySource::factory()->default()->create();
    $product = Product::factory()->create();
    $product->inventoryStocks()->create([
        'inventory_source_id' => $source->id,
        'physical_qty' => 10,
        'reserved_qty' => 0,
        'manage_stock' => true,
    ]);

    $order = Order::factory()->create(['status' => 'pending_payment']);

    // Reserva asociada a la orden.
    app(StockReservationService::class)->reserve($product, 4, "order:{$order->id}", $source);
    expect($product->inventoryStocks()->first()->reserved_qty)->toBe(4);

    $this->post(route('admin.orders.cancel', $order), ['comment' => 'Cliente canceló'])->assertRedirect();

    expect($order->fresh()->status)->toBe('cancelled')
        ->and($product->inventoryStocks()->first()->reserved_qty)->toBe(0);

    $this->assertDatabaseHas('stock_reservations', [
        'reference' => "order:{$order->id}",
        'status' => 'released',
    ]);
});

test('a shipped order cannot be cancelled', function () {
    $order = Order::factory()->create(['status' => 'shipped']);

    $this->post(route('admin.orders.cancel', $order))->assertRedirect();

    expect($order->fresh()->status)->toBe('shipped');
});

test('an invalid status is rejected', function () {
    $order = Order::factory()->create();

    $this->put(route('admin.orders.status', $order), ['status' => 'teleported'])
        ->assertSessionHasErrors('status');
});

test('a viewer cannot change order status', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Solo lectura');
    $this->actingAs($viewer);

    $order = Order::factory()->create();

    $this->put(route('admin.orders.status', $order), ['status' => 'processing'])->assertForbidden();
});

test('a user without sales permission is forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.orders.index'))->assertForbidden();
});
