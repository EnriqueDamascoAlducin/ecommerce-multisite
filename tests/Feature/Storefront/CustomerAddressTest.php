<?php

use App\Models\Customer;
use App\Models\CustomerAddress;

beforeEach(function () {
    $this->customer = Customer::factory()->create();
    $this->actingAs($this->customer, 'customer');
});

function addressPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Ana',
        'last_name' => 'López',
        'line1' => 'Av. Siempre Viva 742',
        'city' => 'CDMX',
        'state' => 'CDMX',
        'postal_code' => '01000',
        'country' => 'MX',
    ], $overrides);
}

test('a customer can list addresses', function () {
    CustomerAddress::factory()->for($this->customer)->create();

    $this->get('/cuenta/direcciones')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('storefront/account/addresses')->has('addresses', 1));
});

test('a customer can add an address', function () {
    $this->post('/cuenta/direcciones', addressPayload())->assertRedirect();

    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $this->customer->id,
        'line1' => 'Av. Siempre Viva 742',
    ]);
});

test('a customer can update an address', function () {
    $address = CustomerAddress::factory()->for($this->customer)->create();

    $this->put("/cuenta/direcciones/{$address->id}", addressPayload(['city' => 'Monterrey']))
        ->assertRedirect();

    expect($address->fresh()->city)->toBe('Monterrey');
});

test('a customer can delete an address', function () {
    $address = CustomerAddress::factory()->for($this->customer)->create();

    $this->delete("/cuenta/direcciones/{$address->id}")->assertRedirect();
    $this->assertDatabaseMissing('customer_addresses', ['id' => $address->id]);
});

test('only one default shipping address is kept', function () {
    $first = CustomerAddress::factory()->for($this->customer)->create(['is_default_shipping' => true]);

    $this->post('/cuenta/direcciones', addressPayload(['is_default_shipping' => '1']))->assertRedirect();

    expect($first->fresh()->is_default_shipping)->toBeFalse();
    expect(CustomerAddress::where('customer_id', $this->customer->id)->where('is_default_shipping', true)->count())->toBe(1);
});

test('a customer cannot modify another customer address', function () {
    $other = CustomerAddress::factory()->for(Customer::factory())->create();

    $this->put("/cuenta/direcciones/{$other->id}", addressPayload())->assertForbidden();
    $this->delete("/cuenta/direcciones/{$other->id}")->assertForbidden();
});
