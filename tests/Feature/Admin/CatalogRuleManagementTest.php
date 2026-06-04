<?php

use App\Models\CatalogPriceRule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
    $this->actingAs($this->admin);
});

function catalogRulePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Verano -15%',
        'description' => 'Descuento de temporada',
        'website_id' => null,
        'category_id' => null,
        'action' => 'percent',
        'value' => 15,
        'priority' => 0,
        'starts_at' => null,
        'ends_at' => null,
        'is_active' => true,
    ], $overrides);
}

test('an admin can create a catalog price rule', function () {
    $this->post(route('admin.catalog-rules.store'), catalogRulePayload())
        ->assertRedirect(route('admin.catalog-rules.index'));

    $this->assertDatabaseHas('catalog_price_rules', ['name' => 'Verano -15%', 'action' => 'percent']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'catalog_rule.created']);
});

test('a percentage value cannot exceed 100', function () {
    $this->post(route('admin.catalog-rules.store'), catalogRulePayload(['value' => 150]))
        ->assertSessionHasErrors('value');
});

test('an admin can update a catalog rule', function () {
    $rule = CatalogPriceRule::factory()->create();

    $this->put(route('admin.catalog-rules.update', $rule), catalogRulePayload(['name' => 'Actualizada']))
        ->assertRedirect();

    expect($rule->fresh()->name)->toBe('Actualizada');
});

test('an admin can delete a catalog rule', function () {
    $rule = CatalogPriceRule::factory()->create();

    $this->delete(route('admin.catalog-rules.destroy', $rule))->assertRedirect();

    $this->assertDatabaseMissing('catalog_price_rules', ['id' => $rule->id]);
});

test('a user without permission cannot manage catalog rules', function () {
    $user = User::factory()->create();
    $user->assignRole('Soporte'); // no incluye promotions.*
    $this->actingAs($user);

    $this->get(route('admin.catalog-rules.index'))->assertForbidden();
});
