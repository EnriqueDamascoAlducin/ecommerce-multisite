<?php

use App\Models\PaymentGatewaySetting;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
    $this->actingAs($this->admin);

    $this->website = Website::factory()->create();
});

test('the payments settings page loads', function () {
    $this->get(route('admin.payments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/payments/index')
            ->has('gateways')
            ->has('websites'));
});

test('an admin saves gateway credentials and mode for a website', function () {
    $this->put(route('admin.payments.update'), [
        'website_id' => $this->website->id,
        'gateway' => 'mercadopago',
        'is_enabled' => true,
        'mode' => 'live',
        'credentials' => ['access_token' => 'APP-USR-xyz', 'public_key' => 'PUB-1'],
    ])->assertRedirect();

    $setting = PaymentGatewaySetting::where('website_id', $this->website->id)
        ->where('gateway', 'mercadopago')
        ->firstOrFail();

    expect($setting->is_enabled)->toBeTrue();
    expect($setting->mode)->toBe('live');
    expect($setting->credential('access_token'))->toBe('APP-USR-xyz');
    expect($setting->credential('public_key'))->toBe('PUB-1');
    $this->assertDatabaseHas('audit_logs', ['action' => 'payment_settings.updated']);
});

test('a blank secret keeps the stored value while other fields update', function () {
    PaymentGatewaySetting::factory()->create([
        'website_id' => $this->website->id,
        'gateway' => 'mercadopago',
        'credentials' => ['access_token' => 'KEEP-ME', 'public_key' => 'OLD'],
    ]);

    $this->put(route('admin.payments.update'), [
        'website_id' => $this->website->id,
        'gateway' => 'mercadopago',
        'is_enabled' => true,
        'credentials' => ['access_token' => '', 'public_key' => 'NEW'],
    ])->assertRedirect();

    $setting = PaymentGatewaySetting::where('website_id', $this->website->id)
        ->where('gateway', 'mercadopago')
        ->firstOrFail();

    // El secreto en blanco se conserva; el campo público sí se actualiza.
    expect($setting->credential('access_token'))->toBe('KEEP-ME');
    expect($setting->credential('public_key'))->toBe('NEW');
});

test('stored credentials are encrypted in the database', function () {
    $this->put(route('admin.payments.update'), [
        'website_id' => $this->website->id,
        'gateway' => 'mercadopago',
        'is_enabled' => true,
        'credentials' => ['access_token' => 'ENCRYPT-ME-456'],
    ]);

    $raw = DB::table('payment_gateway_settings')->value('credentials');

    expect($raw)->not->toContain('ENCRYPT-ME-456');
});

test('the Soporte role cannot manage payment settings', function () {
    $support = User::factory()->create();
    $support->assignRole('Soporte');

    $this->actingAs($support)
        ->get(route('admin.payments.index'))
        ->assertForbidden();
});
