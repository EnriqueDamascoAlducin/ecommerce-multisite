<?php

use App\Domain\Payment\PaymentException;
use App\Domain\Payment\PaymentGatewayRegistry;
use App\Domain\Payment\PaymentService;
use App\Domain\Payment\PaymentStatus;
use App\Models\Order;

function orderWithItem(array $overrides = []): Order
{
    $order = Order::factory()->create($overrides);
    $order->items()->create([
        'sku' => 'SKU-1', 'name' => 'Producto', 'quantity' => 1,
        'unit_price' => $order->total, 'line_total' => $order->total,
    ]);

    return $order;
}

test('only configured gateways are offered as payment methods', function () {
    config()->set('payments.mercadopago.access_token', null);

    $registry = app(PaymentGatewayRegistry::class);

    expect($registry->availableCodes())->toBe(['offline']);

    config()->set('payments.mercadopago.access_token', 'TEST-token');

    expect($registry->availableCodes())->toContain('mercadopago')
        ->and($registry->availableCodes())->toContain('offline');
});

test('starting an offline payment keeps the order pending and records the transaction', function () {
    $order = orderWithItem(['status' => Order::STATUS_PENDING_PAYMENT]);

    $result = app(PaymentService::class)->start($order, 'offline');

    expect($result->status)->toBe(PaymentStatus::Pending)
        ->and($result->requiresRedirect())->toBeFalse()
        ->and($order->fresh()->status)->toBe(Order::STATUS_PENDING_PAYMENT);

    $this->assertDatabaseHas('payment_transactions', [
        'order_id' => $order->id,
        'gateway' => 'offline',
        'status' => 'pending',
    ]);
});

test('starting a payment with an unknown gateway throws', function () {
    $order = orderWithItem();

    expect(fn () => app(PaymentService::class)->start($order, 'paypal'))
        ->toThrow(PaymentException::class);
});

test('starting a payment with an unconfigured gateway throws', function () {
    config()->set('payments.mercadopago.access_token', null);
    $order = orderWithItem();

    expect(fn () => app(PaymentService::class)->start($order, 'mercadopago'))
        ->toThrow(PaymentException::class);
});
