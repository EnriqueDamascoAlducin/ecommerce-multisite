<?php

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create()->assignRole('Super Admin');
    $this->actingAs($this->admin);
});

function invoiceableOrder(): Order
{
    $order = Order::factory()->create([
        'status' => Order::STATUS_PAID,
        'email' => 'buyer@example.com',
    ]);

    $order->items()->createMany([
        ['sku' => 'SKU-1', 'name' => 'Producto A', 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200],
        ['sku' => 'SKU-2', 'name' => 'Producto B', 'quantity' => 1, 'unit_price' => 50, 'line_total' => 50],
    ]);

    return $order;
}

test('an invoice can be generated from a paid order', function () {
    $order = invoiceableOrder();

    $this->post(route('admin.invoices.store'), ['order_id' => $order->id])
        ->assertRedirect(route('admin.orders.show', $order));

    $invoice = Invoice::firstOrFail();

    expect($invoice->order_id)->toBe($order->id)
        ->and($invoice->total)->toEqual($order->total)
        ->and($invoice->items->count())->toBe(2)
        ->and($order->fresh()->status)->toBe(Order::STATUS_INVOICED);
});

test('an invoice cannot be generated for a non-paid order', function () {
    $order = Order::factory()->create(['status' => Order::STATUS_PENDING_PAYMENT]);

    $this->post(route('admin.invoices.store'), ['order_id' => $order->id])
        ->assertSessionHas('error');

    expect(Invoice::count())->toBe(0);
});

test('an invoice cannot be generated twice for the same order', function () {
    $order = invoiceableOrder();

    $this->post(route('admin.invoices.store'), ['order_id' => $order->id]);

    $this->post(route('admin.invoices.store'), ['order_id' => $order->id])
        ->assertSessionHas('error');

    expect(Invoice::count())->toBe(1);
});

test('invoices are listed in the admin', function () {
    $order = invoiceableOrder();
    $this->post(route('admin.invoices.store'), ['order_id' => $order->id]);

    $this->get(route('admin.invoices.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/invoices/index'));
});

test('an invoice can be viewed', function () {
    $order = invoiceableOrder();
    $this->post(route('admin.invoices.store'), ['order_id' => $order->id]);

    $invoice = Invoice::firstOrFail();

    $this->get(route('admin.invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/invoices/show'));
});

test('a pending invoice can be cancelled', function () {
    $order = invoiceableOrder();
    $this->post(route('admin.invoices.store'), ['order_id' => $order->id]);

    $invoice = Invoice::firstOrFail();

    $this->post(route('admin.invoices.cancel', $invoice));

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_CANCELLED)
        ->and($order->fresh()->status)->toBe(Order::STATUS_PAID);
});

test('only users with permission can generate an invoice', function () {
    $user = User::factory()->create()->assignRole('Solo lectura');
    $this->actingAs($user);

    $order = invoiceableOrder();
    $this->post(route('admin.invoices.store'), ['order_id' => $order->id])
        ->assertForbidden();
});

test('generate invoice action is idempotent within a transaction', function () {
    $order = invoiceableOrder();

    $this->post(route('admin.invoices.store'), ['order_id' => $order->id]);

    expect($order->fresh()->status)->toBe(Order::STATUS_INVOICED);

    $this->post(route('admin.invoices.store'), ['order_id' => $order->id])
        ->assertSessionHas('error');

    expect(Invoice::count())->toBe(1);
});
