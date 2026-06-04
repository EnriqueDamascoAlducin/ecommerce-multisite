<?php

use App\Domain\Payment\PaymentGatewayRegistry;
use App\Domain\Payment\PaymentService;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('payments.openpay.merchant_id', 'mdev123');
    config()->set('payments.openpay.private_key', 'sk_test');
    config()->set('payments.openpay.base_url', 'https://sandbox-api.openpay.mx/v1');
});

function fakeCharge(string $status = 'in_progress'): void
{
    Http::fake([
        '*/charges' => Http::response([
            'id' => 'trCHG1',
            'status' => $status,
            'payment_method' => [
                'type' => 'store',
                'reference' => '120010801234567',
                'url' => 'https://sandbox-api.openpay.mx/recibo/trCHG1',
            ],
        ]),
    ]);
}

function openpayWebhook(Order $order, string $status, string $type = 'charge.succeeded', string $txId = 'trCHG1'): array
{
    return [
        'type' => $type,
        'transaction' => [
            'id' => $txId,
            'status' => $status,
            'order_id' => (string) $order->id,
            'amount' => (float) $order->total,
        ],
    ];
}

test('openpay is offered only when configured', function () {
    config()->set('payments.openpay.merchant_id', null);
    expect(app(PaymentGatewayRegistry::class)->availableCodes())->not->toContain('openpay');

    config()->set('payments.openpay.merchant_id', 'mdev123');
    expect(app(PaymentGatewayRegistry::class)->availableCodes())->toContain('openpay');
});

test('starting a payment creates a charge and returns the receipt url', function () {
    fakeCharge();
    $order = Order::factory()->create(['total' => 300, 'status' => Order::STATUS_PENDING_PAYMENT]);

    $result = app(PaymentService::class)->start($order, 'openpay');

    expect($result->requiresRedirect())->toBeTrue()
        ->and($result->redirectUrl)->toBe('https://sandbox-api.openpay.mx/recibo/trCHG1')
        ->and($order->fresh()->status)->toBe(Order::STATUS_PENDING_PAYMENT);

    $this->assertDatabaseHas('payment_transactions', [
        'order_id' => $order->id, 'gateway' => 'openpay', 'status' => 'pending', 'reference' => '120010801234567',
    ]);

    Http::assertSent(function ($request) use ($order) {
        return str_contains($request->url(), '/mdev123/charges')
            && $request['order_id'] === (string) $order->id
            && $request['method'] === 'store'
            && $request->hasHeader('Authorization');
    });
});

test('a completed charge webhook marks the order as paid', function () {
    $order = Order::factory()->create(['status' => Order::STATUS_PENDING_PAYMENT]);

    $this->postJson(route('webhooks.payments', ['gateway' => 'openpay']), openpayWebhook($order, 'completed'))
        ->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_PAID);
    $this->assertDatabaseHas('payment_transactions', ['order_id' => $order->id, 'gateway' => 'openpay', 'status' => 'paid']);
    $this->assertDatabaseHas('payment_webhook_events', ['gateway' => 'openpay', 'event_id' => 'trCHG1:paid', 'status' => 'processed']);
});

test('a failed charge webhook marks the order as failed', function () {
    $order = Order::factory()->create(['status' => Order::STATUS_PENDING_PAYMENT]);

    $this->postJson(route('webhooks.payments', ['gateway' => 'openpay']), openpayWebhook($order, 'failed', 'charge.failed'))
        ->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_FAILED);
});

test('the openpay webhook is idempotent', function () {
    $order = Order::factory()->create(['status' => Order::STATUS_PENDING_PAYMENT]);
    $body = openpayWebhook($order, 'completed');

    $this->postJson(route('webhooks.payments', ['gateway' => 'openpay']), $body)->assertOk();
    $this->postJson(route('webhooks.payments', ['gateway' => 'openpay']), $body)->assertOk();

    expect(PaymentTransaction::where('order_id', $order->id)->where('status', 'paid')->count())->toBe(1)
        ->and($order->fresh()->status)->toBe(Order::STATUS_PAID);
});

test('a verification notification is acknowledged and ignored', function () {
    $this->postJson(route('webhooks.payments', ['gateway' => 'openpay']), [
        'type' => 'verification',
        'verification_code' => 'abc123',
    ])->assertOk();

    $this->assertDatabaseCount('payment_transactions', 0);
});

test('a webhook with wrong basic auth is rejected when a secret is configured', function () {
    config()->set('payments.openpay.webhook_user', 'hookuser');
    config()->set('payments.openpay.webhook_password', 'hookpass');

    $order = Order::factory()->create(['status' => Order::STATUS_PENDING_PAYMENT]);

    // Sin credenciales Basic → la notificación se ignora (no transiciona la orden).
    $this->postJson(route('webhooks.payments', ['gateway' => 'openpay']), openpayWebhook($order, 'completed'))
        ->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_PENDING_PAYMENT);
    $this->assertDatabaseCount('payment_transactions', 0);
});
