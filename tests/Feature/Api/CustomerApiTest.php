<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Store;
use App\Models\Website;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true, 'code' => 'demo']);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    $this->customer = Customer::factory()->create([
        'website_id' => $this->website->id,
        'email' => 'cliente@example.com',
        'password' => 'secret123',
    ]);
});

test('a customer can log in and receive a token', function () {
    $this->postJson('/api/v1/login', [
        'email' => 'cliente@example.com',
        'password' => 'secret123',
        'device_name' => 'app-movil',
    ])
        ->assertOk()
        ->assertJsonStructure(['token', 'customer' => ['id', 'name', 'email']])
        ->assertJsonPath('customer.email', 'cliente@example.com');

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_type' => Customer::class,
        'tokenable_id' => $this->customer->id,
    ]);
});

test('login fails with wrong credentials', function () {
    $this->postJson('/api/v1/login', [
        'email' => 'cliente@example.com',
        'password' => 'incorrecta',
    ])->assertStatus(422);
});

test('an authenticated customer can fetch their profile', function () {
    Sanctum::actingAs($this->customer);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'cliente@example.com');
});

test('a customer lists only their own orders', function () {
    $mine = Order::factory()->create(['customer_id' => $this->customer->id]);
    $other = Customer::factory()->create(['website_id' => $this->website->id]);
    Order::factory()->create(['customer_id' => $other->id]);

    Sanctum::actingAs($this->customer);

    $this->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.number', $mine->number);
});

test('a customer cannot view another customers order', function () {
    $other = Customer::factory()->create(['website_id' => $this->website->id]);
    $foreign = Order::factory()->create(['customer_id' => $other->id]);

    Sanctum::actingAs($this->customer);

    $this->getJson("/api/v1/orders/{$foreign->id}")->assertForbidden();
});

test('order endpoints require authentication', function () {
    $this->getJson('/api/v1/orders')->assertUnauthorized();
});
