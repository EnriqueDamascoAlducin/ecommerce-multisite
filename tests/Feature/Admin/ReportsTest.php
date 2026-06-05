<?php

use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
    $this->actingAs($this->admin);
});

/**
 * @param  list<array{sku: string, name: string, quantity: int, unit_price: int, line_total: int}>  $items
 * @param  array<string, mixed>  $attrs
 */
function orderWithItems(string $status, int $total, array $items, array $attrs = []): Order
{
    $order = Order::factory()->create(array_merge([
        'status' => $status,
        'total' => $total,
        'placed_at' => now(),
    ], $attrs));

    foreach ($items as $item) {
        $order->items()->create($item);
    }

    return $order;
}

test('the report sums revenue and units only for paid orders', function () {
    orderWithItems(Order::STATUS_PAID, 300, [['sku' => 'AAA', 'name' => 'A', 'quantity' => 2, 'unit_price' => 150, 'line_total' => 300]]);
    orderWithItems(Order::STATUS_SHIPPED, 200, [['sku' => 'BBB', 'name' => 'B', 'quantity' => 3, 'unit_price' => 66, 'line_total' => 200]]);
    orderWithItems(Order::STATUS_PENDING_PAYMENT, 999, [['sku' => 'CCC', 'name' => 'C', 'quantity' => 5, 'unit_price' => 200, 'line_total' => 999]]);

    $this->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->where('summary.revenue', '500.00')
            ->where('summary.paid_orders', 2)
            ->where('summary.total_orders', 3)
            ->where('summary.units_sold', 5)
        );
});

test('dashboard shows the sales report data', function () {
    orderWithItems(Order::STATUS_PAID, 300, [['sku' => 'AAA', 'name' => 'A', 'quantity' => 2, 'unit_price' => 150, 'line_total' => 300]]);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->where('summary.revenue', '300.00')
            ->where('summary.paid_orders', 1)
        );
});

test('top products aggregates quantity by sku across orders', function () {
    orderWithItems(Order::STATUS_PAID, 200, [['sku' => 'AAA', 'name' => 'Playera', 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200]]);
    orderWithItems(Order::STATUS_PAID, 300, [['sku' => 'AAA', 'name' => 'Playera', 'quantity' => 3, 'unit_price' => 100, 'line_total' => 300]]);

    $this->get(route('admin.reports.index'))
        ->assertInertia(fn ($page) => $page
            ->where('topProducts.0.sku', 'AAA')
            ->where('topProducts.0.quantity', 5)
            ->where('topProducts.0.revenue', '500.00')
        );
});

test('filtering by store narrows the revenue', function () {
    $a = orderWithItems(Order::STATUS_PAID, 300, [['sku' => 'AAA', 'name' => 'A', 'quantity' => 1, 'unit_price' => 300, 'line_total' => 300]]);
    orderWithItems(Order::STATUS_PAID, 200, [['sku' => 'BBB', 'name' => 'B', 'quantity' => 1, 'unit_price' => 200, 'line_total' => 200]]);

    $this->get(route('admin.reports.index', ['store_id' => $a->store_id]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.revenue', '300.00')
            ->where('summary.paid_orders', 1)
        );
});

test('orders outside the date range are excluded', function () {
    orderWithItems(Order::STATUS_PAID, 300, [['sku' => 'AAA', 'name' => 'A', 'quantity' => 1, 'unit_price' => 300, 'line_total' => 300]], ['placed_at' => now()->subDays(90)]);

    $this->get(route('admin.reports.index'))
        ->assertInertia(fn ($page) => $page
            ->where('summary.revenue', '0.00')
            ->where('summary.paid_orders', 0)
        );
});

test('a user without the reports permission is forbidden', function () {
    $user = User::factory()->create();
    $user->assignRole('Marketing'); // no incluye reports.view
    $this->actingAs($user);

    $this->get(route('admin.reports.index'))->assertForbidden();
});
