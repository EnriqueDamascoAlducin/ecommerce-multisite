<?php

use App\Domain\Payment\Gateways\MercadoPagoGateway;
use App\Domain\Payment\Gateways\OfflineGateway;
use App\Domain\Payment\PaymentSettings;
use App\Models\Order;
use App\Models\PaymentGatewaySetting;
use App\Models\Store;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

test('a website credential takes precedence over the env config', function () {
    config()->set('payments.mercadopago.access_token', 'ENV-token');

    $website = Website::factory()->create();
    PaymentGatewaySetting::factory()->create([
        'website_id' => $website->id,
        'gateway' => 'mercadopago',
        'is_enabled' => true,
        'credentials' => ['access_token' => 'DB-token'],
    ]);

    $settings = app(PaymentSettings::class);
    $settings->usingWebsite($website->id);

    expect($settings->value('mercadopago', 'access_token'))->toBe('DB-token');
});

test('without a website setting it falls back to the env config', function () {
    config()->set('payments.mercadopago.access_token', 'ENV-token');

    $settings = app(PaymentSettings::class);
    $settings->usingWebsite(null);

    expect($settings->value('mercadopago', 'access_token'))->toBe('ENV-token');
});

test('a gateway disabled for the website is not available', function () {
    config()->set('payments.mercadopago.access_token', 'ENV-token');

    $website = Website::factory()->create();
    PaymentGatewaySetting::factory()->disabled()->create([
        'website_id' => $website->id,
        'gateway' => 'mercadopago',
        'credentials' => ['access_token' => 'DB-token'],
    ]);

    app(PaymentSettings::class)->usingWebsite($website->id);

    expect((new MercadoPagoGateway)->isAvailable())->toBeFalse();
});

test('offline can be disabled per website', function () {
    $website = Website::factory()->create();
    PaymentGatewaySetting::factory()->disabled()->create([
        'website_id' => $website->id,
        'gateway' => 'offline',
        'credentials' => [],
    ]);

    app(PaymentSettings::class)->usingWebsite($website->id);

    expect((new OfflineGateway)->isAvailable())->toBeFalse();
});

test('credentials are stored encrypted at rest', function () {
    $website = Website::factory()->create();
    PaymentGatewaySetting::factory()->create([
        'website_id' => $website->id,
        'credentials' => ['access_token' => 'SUPER-SECRET-123'],
    ]);

    $raw = DB::table('payment_gateway_settings')->value('credentials');

    expect($raw)->not->toContain('SUPER-SECRET-123');
});

test('the webhook uses the website credentials to confirm the payment', function () {
    // Sin token en env: la confirmación sólo puede usar el del sitio.
    config()->set('payments.mercadopago.access_token', null);

    $website = Website::factory()->create(['is_default' => true, 'code' => 'demo']);
    $store = Store::factory()->create(['website_id' => $website->id, 'is_default' => true, 'is_active' => true]);

    PaymentGatewaySetting::factory()->create([
        'website_id' => $website->id,
        'gateway' => 'mercadopago',
        'is_enabled' => true,
        'credentials' => ['access_token' => 'WEBSITE-TOKEN'],
    ]);

    $order = Order::factory()->create([
        'website_id' => $website->id,
        'store_id' => $store->id,
        'status' => Order::STATUS_PENDING_PAYMENT,
    ]);

    Http::fake([
        '*/v1/payments/*' => Http::response([
            'id' => 'PAY-WS', 'status' => 'approved', 'external_reference' => (string) $order->id,
        ]),
    ]);

    $this->postJson(route('webhooks.payments', ['gateway' => 'mercadopago', 'website' => $website->id]), [
        'type' => 'payment', 'data' => ['id' => 'PAY-WS'],
    ])->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_PAID);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer WEBSITE-TOKEN'));
});
