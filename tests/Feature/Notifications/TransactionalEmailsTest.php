<?php

use App\Models\Customer;
use App\Models\InventorySource;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use App\Models\Website;
use App\Notifications\CustomerRegistered;
use App\Notifications\OrderCreated;
use App\Notifications\PaymentApproved;
use App\Notifications\PaymentFailed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
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

function payload(array $overrides = []): array
{
    return array_merge([
        'email' => 'guest@example.com',
        'payment_method' => 'offline',
        'shipping_method_code' => 'flat_rate',
        'billing_same' => '1',
        'shipping' => [
            'first_name' => 'Ana',
            'last_name' => 'López',
            'line1' => 'Calle 123',
            'city' => 'CDMX',
            'state' => 'CDMX',
            'postal_code' => '01000',
            'country' => 'MX',
        ],
    ], $overrides);
}

test('OrderCreated notification is sent after placing an order', function () {
    Notification::fake();

    $product = sellableProduct($this->store, $this->source, 200, stock: 10);
    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);
    $this->post(route('checkout.store'), payload());

    $order = Order::firstOrFail();

    Notification::assertSentTo(
        Notification::route('mail', 'guest@example.com'),
        OrderCreated::class,
        function ($notification, $channels, $notifiable) use ($order) {
            return $notification->order->id === $order->id;
        },
    );
});

test('OrderCreated notification is not sent when checkout fails', function () {
    Notification::fake();

    $this->post(route('checkout.store'), payload())->assertRedirect(route('checkout.index'));

    Notification::assertNothingSent();
});

test('PaymentApproved notification is sent when payment webhook confirms approval', function () {
    Notification::fake();

    $order = Order::factory()->create([
        'store_id' => $this->store->id,
        'status' => Order::STATUS_PENDING_PAYMENT,
        'email' => 'comprador@example.com',
    ]);

    config()->set('payments.mercadopago.access_token', 'TEST-token');

    Http::fake([
        '*/v1/payments/*' => Http::response([
            'id' => 'PAY-77',
            'status' => 'approved',
            'external_reference' => (string) $order->id,
        ]),
    ]);

    $this->postJson(route('webhooks.payments', ['gateway' => 'mercadopago']), [
        'type' => 'payment',
        'data' => ['id' => 'PAY-77'],
    ])->assertOk();

    Notification::assertSentTo(
        Notification::route('mail', 'comprador@example.com'),
        PaymentApproved::class,
        fn ($notification) => $notification->order->id === $order->id,
    );
});

test('PaymentFailed notification is sent when payment webhook reports failure', function () {
    Notification::fake();

    $order = Order::factory()->create([
        'store_id' => $this->store->id,
        'status' => Order::STATUS_PENDING_PAYMENT,
        'email' => 'comprador@example.com',
    ]);

    config()->set('payments.mercadopago.access_token', 'TEST-token');

    Http::fake([
        '*/v1/payments/*' => Http::response([
            'id' => 'PAY-99',
            'status' => 'rejected',
            'external_reference' => (string) $order->id,
        ]),
    ]);

    $this->postJson(route('webhooks.payments', ['gateway' => 'mercadopago']), [
        'type' => 'payment',
        'data' => ['id' => 'PAY-99'],
    ])->assertOk();

    Notification::assertSentTo(
        Notification::route('mail', 'comprador@example.com'),
        PaymentFailed::class,
        fn ($notification) => $notification->order->id === $order->id,
    );
});

test('payment notifications are not sent for pending or refunded transitions', function () {
    Notification::fake();

    $order = Order::factory()->create([
        'store_id' => $this->store->id,
        'status' => Order::STATUS_PENDING_PAYMENT,
        'email' => 'comprador@example.com',
    ]);

    config()->set('payments.mercadopago.access_token', 'TEST-token');

    // Pending (in_process)
    Http::fake([
        '*/v1/payments/*' => Http::response([
            'id' => 'PAY-1',
            'status' => 'in_process',
            'external_reference' => (string) $order->id,
        ]),
    ]);

    $this->postJson(route('webhooks.payments', ['gateway' => 'mercadopago']), [
        'type' => 'payment',
        'data' => ['id' => 'PAY-1'],
    ])->assertOk();

    Notification::assertNothingSent();
});

test('CustomerRegistered notification is sent after customer registration', function () {
    Notification::fake();

    $this->post('/cuenta/registro', [
        'name' => 'Nuevo Cliente',
        'email' => 'nuevo@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect();

    $customer = Customer::where('email', 'nuevo@example.com')->firstOrFail();

    Notification::assertSentTo(
        $customer,
        CustomerRegistered::class,
        fn ($notification) => $notification->customer->id === $customer->id,
    );
});

test('CustomerRegistered notification is not sent on failed registration', function () {
    Notification::fake();

    $this->post('/cuenta/registro', [
        'name' => '',
        'email' => 'not-an-email',
        'password' => '123',
    ])->assertSessionHasErrors();

    Notification::assertNothingSent();
});
