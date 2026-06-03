<?php

use App\Domain\Inventory\StockReservationService;
use App\Domain\Payment\PaymentService;
use App\Models\InventorySource;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\ShippingMethod;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use App\Models\Website;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('payments.mercadopago.access_token', 'TEST-token');

    $this->website = Website::factory()->create(['is_default' => true, 'code' => 'demo']);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    $this->source = InventorySource::factory()->default()->create();

    $method = ShippingMethod::factory()->create(['code' => 'flat_rate', 'type' => 'flat_rate']);
    $ssm = StoreShippingMethod::factory()->create([
        'store_id' => $this->store->id,
        'shipping_method_id' => $method->id,
    ]);
    $ssm->rates()->create(['min_subtotal' => 0, 'max_subtotal' => null, 'amount' => 99]);
});

function mpPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'guest@example.com',
        'payment_method' => 'mercadopago',
        'shipping_method_code' => 'flat_rate',
        'billing_same' => '1',
        'shipping' => [
            'first_name' => 'Ana', 'last_name' => 'López', 'line1' => 'Calle 123',
            'city' => 'CDMX', 'state' => 'CDMX', 'postal_code' => '01000', 'country' => 'MX',
        ],
    ], $overrides);
}

function fakePayment(Order $order, string $status, string $paymentId = 'PAY-1'): void
{
    Http::fake([
        '*/v1/payments/*' => Http::response([
            'id' => $paymentId,
            'status' => $status,
            'external_reference' => (string) $order->id,
        ]),
    ]);
}

test('a guest checkout with mercadopago redirects to the hosted checkout', function () {
    Http::fake(['*/checkout/preferences' => Http::response(['id' => 'pref-99', 'init_point' => 'https://mp.test/go'])]);

    $product = sellableProduct($this->store, $this->source, 150, stock: 10);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);

    $this->post(route('checkout.store'), mpPayload())->assertRedirect('https://mp.test/go');

    $order = Order::firstOrFail();
    expect($order->status)->toBe(Order::STATUS_PENDING_PAYMENT);
    $this->assertDatabaseHas('payment_transactions', [
        'order_id' => $order->id, 'gateway' => 'mercadopago', 'status' => 'pending', 'reference' => 'pref-99',
    ]);
});

test('creating a preference sends the order items and external reference', function () {
    Http::fake(['*/checkout/preferences' => Http::response(['id' => 'pref-1', 'init_point' => 'https://mp.test/x'])]);

    $order = Order::factory()->create(['total' => 250]);
    $order->items()->create(['sku' => 'A', 'name' => 'Lámpara', 'quantity' => 2, 'unit_price' => 125, 'line_total' => 250]);

    app(PaymentService::class)->start($order, 'mercadopago');

    Http::assertSent(function ($request) use ($order) {
        return str_contains($request->url(), '/checkout/preferences')
            && $request['external_reference'] === (string) $order->id
            && $request['items'][0]['title'] === 'Lámpara'
            && $request->hasHeader('Authorization', 'Bearer TEST-token');
    });
});

test('an approved payment webhook marks the order as paid', function () {
    $order = Order::factory()->create(['status' => Order::STATUS_PENDING_PAYMENT]);
    fakePayment($order, 'approved', 'PAY-7');

    $this->postJson(route('webhooks.payments', ['gateway' => 'mercadopago']), [
        'type' => 'payment', 'data' => ['id' => 'PAY-7'],
    ])->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_PAID);
    $this->assertDatabaseHas('payment_transactions', ['order_id' => $order->id, 'gateway' => 'mercadopago', 'status' => 'paid']);
    $this->assertDatabaseHas('payment_webhook_events', ['gateway' => 'mercadopago', 'event_id' => 'PAY-7:paid', 'status' => 'processed']);
});

test('a rejected payment webhook marks the order as failed', function () {
    $order = Order::factory()->create(['status' => Order::STATUS_PENDING_PAYMENT]);
    fakePayment($order, 'rejected');

    $this->postJson(route('webhooks.payments', ['gateway' => 'mercadopago']), [
        'type' => 'payment', 'data' => ['id' => 'PAY-1'],
    ])->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_FAILED);
});

test('a pending payment webhook moves the order to payment review', function () {
    $order = Order::factory()->create(['status' => Order::STATUS_PENDING_PAYMENT]);
    fakePayment($order, 'in_process');

    $this->postJson(route('webhooks.payments', ['gateway' => 'mercadopago']), [
        'type' => 'payment', 'data' => ['id' => 'PAY-1'],
    ])->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_PAYMENT_REVIEW);
});

test('the payment webhook is idempotent', function () {
    $order = Order::factory()->create(['status' => Order::STATUS_PENDING_PAYMENT]);
    fakePayment($order, 'approved', 'PAY-9');

    $body = ['type' => 'payment', 'data' => ['id' => 'PAY-9']];
    $this->postJson(route('webhooks.payments', ['gateway' => 'mercadopago']), $body)->assertOk();
    $this->postJson(route('webhooks.payments', ['gateway' => 'mercadopago']), $body)->assertOk();

    expect(PaymentTransaction::where('order_id', $order->id)->where('status', 'paid')->count())->toBe(1)
        ->and($order->fresh()->status)->toBe(Order::STATUS_PAID);
});

test('a refunded payment webhook releases the reserved stock', function () {
    $order = Order::factory()->create(['status' => Order::STATUS_PAID]);
    $product = sellableProduct($this->store, $this->source, 100, stock: 10);
    app(StockReservationService::class)->reserve($product, 2, "order:{$order->id}", $this->source);

    fakePayment($order, 'refunded');

    $this->postJson(route('webhooks.payments', ['gateway' => 'mercadopago']), [
        'type' => 'payment', 'data' => ['id' => 'PAY-1'],
    ])->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_REFUNDED)
        ->and($product->inventoryStocks()->first()->reserved_qty)->toBe(0);
    $this->assertDatabaseHas('stock_reservations', ['reference' => "order:{$order->id}", 'status' => 'released']);
});

test('a webhook for an unknown gateway returns 404', function () {
    $this->postJson(route('webhooks.payments', ['gateway' => 'stripe']), [
        'type' => 'payment', 'data' => ['id' => 'PAY-1'],
    ])->assertNotFound();
});

test('a non-payment notification is ignored', function () {
    $order = Order::factory()->create(['status' => Order::STATUS_PENDING_PAYMENT]);

    $this->postJson(route('webhooks.payments', ['gateway' => 'mercadopago']), [
        'type' => 'plan', 'data' => ['id' => 'X'],
    ])->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_PENDING_PAYMENT);
    $this->assertDatabaseCount('payment_transactions', 0);
});
