<?php

use App\Models\CustomerGroup;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
    $this->actingAs($this->admin);

    $this->website = Website::factory()->create();
});

function groupPayload(array $overrides = []): array
{
    return array_merge([
        'website_id' => null,
        'name' => 'Mayorista',
        'code' => 'mayorista',
        'description' => 'Clientes mayoristas',
        'color' => '#22c55e',
        'is_default' => false,
    ], $overrides);
}

test('an admin can create a customer group', function () {
    $this->post(route('admin.customer-groups.store'), groupPayload(['website_id' => $this->website->id]))
        ->assertRedirect(route('admin.customer-groups.index'));

    $this->assertDatabaseHas('customer_groups', [
        'website_id' => $this->website->id,
        'code' => 'mayorista',
        'name' => 'Mayorista',
    ]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'customer_group.created']);
});

test('the code is unique per website but reusable across websites', function () {
    CustomerGroup::factory()->create(['website_id' => $this->website->id, 'code' => 'vip']);

    $this->post(route('admin.customer-groups.store'), groupPayload(['website_id' => $this->website->id, 'code' => 'vip']))
        ->assertSessionHasErrors('code');

    $other = Website::factory()->create();
    $this->post(route('admin.customer-groups.store'), groupPayload(['website_id' => $other->id, 'code' => 'vip']))
        ->assertRedirect();
});

test('marking a group as default unmarks the others of the same website', function () {
    $first = CustomerGroup::factory()->default()->create(['website_id' => $this->website->id]);

    $this->post(route('admin.customer-groups.store'), groupPayload([
        'website_id' => $this->website->id,
        'code' => 'nuevo-default',
        'is_default' => true,
    ]))->assertRedirect();

    expect($first->fresh()->is_default)->toBeFalse();
    expect(CustomerGroup::where('website_id', $this->website->id)->where('is_default', true)->count())->toBe(1);
});

test('an invalid color is rejected', function () {
    $this->post(route('admin.customer-groups.store'), groupPayload(['website_id' => $this->website->id, 'color' => 'green']))
        ->assertSessionHasErrors('color');
});

test('the Soporte role can view but not create groups', function () {
    $support = User::factory()->create();
    $support->assignRole('Soporte');

    $this->actingAs($support)->get(route('admin.customer-groups.index'))->assertOk();
    $this->actingAs($support)->get(route('admin.customer-groups.create'))->assertForbidden();
});
