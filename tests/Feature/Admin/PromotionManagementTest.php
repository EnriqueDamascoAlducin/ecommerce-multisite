<?php

use App\Models\CartPriceRule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
    $this->actingAs($this->admin);
});

function rulePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Bienvenida 10%',
        'description' => 'Cupón de bienvenida',
        'website_id' => null,
        'coupon_code' => 'BIENVENIDA',
        'action' => 'percent',
        'value' => 10,
        'min_subtotal' => null,
        'starts_at' => null,
        'ends_at' => null,
        'is_active' => true,
        'usage_limit' => 100,
    ], $overrides);
}

test('an admin can create a cart price rule', function () {
    $this->post(route('admin.promotions.store'), rulePayload())->assertRedirect(route('admin.promotions.index'));

    $this->assertDatabaseHas('cart_price_rules', ['coupon_code' => 'BIENVENIDA', 'action' => 'percent']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'promotion.created']);
});

test('the coupon code must be unique', function () {
    CartPriceRule::factory()->coupon('DUPLICADO')->create();

    $this->post(route('admin.promotions.store'), rulePayload(['coupon_code' => 'DUPLICADO']))
        ->assertSessionHasErrors('coupon_code');
});

test('a percentage value cannot exceed 100', function () {
    $this->post(route('admin.promotions.store'), rulePayload(['value' => 150]))
        ->assertSessionHasErrors('value');
});

test('an admin can update a rule', function () {
    $rule = CartPriceRule::factory()->coupon('EDITAR')->create();

    $this->put(route('admin.promotions.update', $rule), rulePayload(['coupon_code' => 'EDITAR', 'name' => 'Actualizada']))
        ->assertRedirect();

    expect($rule->fresh()->name)->toBe('Actualizada');
});

test('an admin can delete a rule', function () {
    $rule = CartPriceRule::factory()->create();

    $this->delete(route('admin.promotions.destroy', $rule))->assertRedirect();

    $this->assertDatabaseMissing('cart_price_rules', ['id' => $rule->id]);
});

test('a user without permission cannot manage promotions', function () {
    $user = User::factory()->create();
    $user->assignRole('Soporte'); // no incluye promotions.*
    $this->actingAs($user);

    $this->get(route('admin.promotions.index'))->assertForbidden();
});
