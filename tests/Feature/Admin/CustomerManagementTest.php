<?php

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
    $this->actingAs($this->admin);

    $this->website = Website::factory()->create();
    $this->group = CustomerGroup::factory()->create(['website_id' => $this->website->id]);
});

function customerPayload(array $overrides = []): array
{
    return array_merge([
        'website_id' => null,
        'group_id' => null,
        'name' => 'Juan Pérez',
        'email' => 'juan@example.com',
        'phone' => '5551234567',
        'password' => 'password',
        'addresses' => [],
    ], $overrides);
}

function addressData(array $overrides = []): array
{
    return array_merge([
        'label' => 'Casa',
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'company' => null,
        'phone' => null,
        'line1' => 'Calle 1',
        'line2' => null,
        'neighborhood' => 'Centro',
        'city' => 'CDMX',
        'state' => 'CDMX',
        'postal_code' => '01000',
        'country' => 'MX',
        'is_default_shipping' => true,
        'is_default_billing' => true,
    ], $overrides);
}

test('an admin can create a customer with an address and a group', function () {
    $this->post(route('admin.customers.store'), customerPayload([
        'website_id' => $this->website->id,
        'group_id' => $this->group->id,
        'addresses' => [addressData()],
    ]))->assertRedirect(route('admin.customers.index'));

    $this->assertDatabaseHas('customers', [
        'email' => 'juan@example.com',
        'website_id' => $this->website->id,
        'group_id' => $this->group->id,
    ]);
    $this->assertDatabaseHas('customer_addresses', ['line1' => 'Calle 1', 'neighborhood' => 'Centro', 'is_default_shipping' => true]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'customer.created']);
});

test('the email is unique per website but reusable across websites', function () {
    Customer::factory()->create(['website_id' => $this->website->id, 'email' => 'dup@example.com']);

    $this->post(route('admin.customers.store'), customerPayload(['website_id' => $this->website->id, 'email' => 'dup@example.com']))
        ->assertSessionHasErrors('email');

    $other = Website::factory()->create();
    $this->post(route('admin.customers.store'), customerPayload(['website_id' => $other->id, 'email' => 'dup@example.com']))
        ->assertRedirect();
});

test('a group from another website is rejected', function () {
    $foreignGroup = CustomerGroup::factory()->create(['website_id' => Website::factory()->create()->id]);

    $this->post(route('admin.customers.store'), customerPayload([
        'website_id' => $this->website->id,
        'group_id' => $foreignGroup->id,
    ]))->assertSessionHasErrors('group_id');
});

test('updating with a blank password keeps the current one', function () {
    $customer = Customer::factory()->create(['website_id' => $this->website->id]);
    $original = $customer->password;

    $this->put(route('admin.customers.update', $customer), customerPayload([
        'email' => $customer->email,
        'name' => 'Nuevo Nombre',
        'password' => '',
    ]))->assertRedirect();

    expect($customer->fresh()->password)->toBe($original)
        ->and($customer->fresh()->name)->toBe('Nuevo Nombre');
});

test('addresses are synced and a single default is enforced', function () {
    $customer = Customer::factory()->create(['website_id' => $this->website->id]);

    $this->put(route('admin.customers.update', $customer), customerPayload([
        'email' => $customer->email,
        'addresses' => [
            addressData(['line1' => 'A', 'is_default_shipping' => true]),
            addressData(['line1' => 'B', 'is_default_shipping' => true]),
        ],
    ]))->assertRedirect();

    expect($customer->addresses()->count())->toBe(2)
        ->and($customer->addresses()->where('is_default_shipping', true)->count())->toBe(1);

    // Volver a guardar con una sola dirección elimina la otra.
    $this->put(route('admin.customers.update', $customer), customerPayload([
        'email' => $customer->email,
        'addresses' => [addressData(['line1' => 'A'])],
    ]))->assertRedirect();

    expect($customer->fresh()->addresses()->count())->toBe(1);
});

test('the edit page provides websites and addresses for a customer without addresses', function () {
    $customer = Customer::factory()->create(['website_id' => $this->website->id]);

    $this->get(route('admin.customers.edit', $customer))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('websites')
            ->where('customer.addresses', []));
});

test('the grid can be filtered by group', function () {
    $other = CustomerGroup::factory()->create(['website_id' => $this->website->id]);
    Customer::factory()->create(['website_id' => $this->website->id, 'group_id' => $this->group->id]);
    Customer::factory()->create(['website_id' => $this->website->id, 'group_id' => $other->id]);

    $this->get(route('admin.customers.index', ['group_id' => $this->group->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('customers.data', 1));
});

test('deleting a customer keeps their orders as guest orders', function () {
    $customer = Customer::factory()->create(['website_id' => $this->website->id]);
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    $this->delete(route('admin.customers.destroy', $customer))->assertRedirect();

    $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'customer_id' => null]);
});

test('the Soporte role can view but not create customers', function () {
    $support = User::factory()->create();
    $support->assignRole('Soporte');

    $this->actingAs($support)->get(route('admin.customers.index'))->assertOk();
    $this->actingAs($support)->get(route('admin.customers.create'))->assertForbidden();
});
