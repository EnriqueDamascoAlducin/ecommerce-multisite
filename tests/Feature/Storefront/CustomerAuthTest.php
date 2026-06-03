<?php

use App\Models\Customer;
use App\Models\Store;
use App\Models\Website;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true]);
    Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
});

test('the register page is publicly accessible', function () {
    $this->get('/cuenta/registro')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('storefront/auth/register'));
});

test('a customer can register and is scoped to the current website', function () {
    $this->post('/cuenta/registro', [
        'name' => 'Ana Cliente',
        'email' => 'ana@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('customer.account'));

    $this->assertDatabaseHas('customers', [
        'email' => 'ana@example.com',
        'website_id' => $this->website->id,
    ]);
    $this->assertAuthenticatedAs(Customer::where('email', 'ana@example.com')->first(), 'customer');
});

test('the same email can register on a different website', function () {
    $otherWebsite = Website::factory()->create();
    Customer::factory()->create(['website_id' => $otherWebsite->id, 'email' => 'dup@example.com']);

    $this->post('/cuenta/registro', [
        'name' => 'Dup',
        'email' => 'dup@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect();

    expect(Customer::where('email', 'dup@example.com')->count())->toBe(2);
});

test('the email must be unique within the same website', function () {
    Customer::factory()->create(['website_id' => $this->website->id, 'email' => 'taken@example.com']);

    $this->post('/cuenta/registro', [
        'name' => 'Otro',
        'email' => 'taken@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('email');
});

test('a customer can log in with valid credentials', function () {
    Customer::factory()->create([
        'website_id' => $this->website->id,
        'email' => 'login@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $this->post('/cuenta/login', [
        'email' => 'login@example.com',
        'password' => 'secret123',
    ])->assertRedirect(route('customer.account'));

    $this->assertAuthenticated('customer');
});

test('login fails with invalid credentials', function () {
    Customer::factory()->create([
        'website_id' => $this->website->id,
        'email' => 'login@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $this->post('/cuenta/login', [
        'email' => 'login@example.com',
        'password' => 'wrong',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('customer');
});

test('a customer can log out', function () {
    $customer = Customer::factory()->create(['website_id' => $this->website->id]);

    $this->actingAs($customer, 'customer')
        ->post('/cuenta/logout')
        ->assertRedirect(route('home'));

    $this->assertGuest('customer');
});

test('guests are redirected from the account area to the customer login', function () {
    $this->get('/cuenta')->assertRedirect(route('customer.login'));
});

test('an authenticated customer can view the account page', function () {
    $customer = Customer::factory()->create(['website_id' => $this->website->id]);

    $this->actingAs($customer, 'customer')
        ->get('/cuenta')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('storefront/account/profile'));
});
