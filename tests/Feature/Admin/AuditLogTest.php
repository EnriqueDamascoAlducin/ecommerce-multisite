<?php

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
    $this->actingAs($this->admin);
});

function auditEntry(string $action, ?int $userId = null): AuditLog
{
    return AuditLog::create([
        'user_id' => $userId,
        'action' => $action,
        'description' => "Acción {$action}",
        'ip_address' => '127.0.0.1',
    ]);
}

test('the audit viewer lists logs', function () {
    auditEntry('product.created', $this->admin->id);
    auditEntry('order.cancelled', $this->admin->id);

    $this->get(route('admin.audit.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/audit/index')
            ->has('logs.data', 2)
            ->has('actions', 2)
        );
});

test('logs can be filtered by action', function () {
    auditEntry('product.created', $this->admin->id);
    auditEntry('order.cancelled', $this->admin->id);

    $this->get(route('admin.audit.index', ['action' => 'order.cancelled']))
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'order.cancelled')
        );
});

test('creating a shipment writes an audit log', function () {
    $order = Order::factory()->create(['status' => Order::STATUS_PAID]);
    $item = $order->items()->create(['sku' => 'SKU1', 'name' => 'Producto', 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200]);

    $this->post(route('admin.shipments.store'), [
        'order_id' => $order->id,
        'items' => [['order_item_id' => $item->id, 'quantity' => 2]],
    ])->assertRedirect();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'shipment.created',
        'user_id' => $this->admin->id,
    ]);
});

test('a user without the audit permission is forbidden', function () {
    $user = User::factory()->create();
    $user->assignRole('Marketing'); // no incluye audit.view
    $this->actingAs($user);

    $this->get(route('admin.audit.index'))->assertForbidden();
});
