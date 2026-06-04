<?php

use App\Models\ProductLabel;
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

function labelPayload(array $overrides = []): array
{
    return array_merge([
        'website_id' => null,
        'text' => 'Oferta',
        'text_color' => '#ffffff',
        'background_color' => '#dc2626',
        'is_active' => true,
        'sort_order' => 0,
    ], $overrides);
}

test('an admin can create a product label', function () {
    $this->post(route('admin.product-labels.store'), labelPayload(['website_id' => $this->website->id]))
        ->assertRedirect(route('admin.product-labels.index'));

    $this->assertDatabaseHas('product_labels', [
        'website_id' => $this->website->id,
        'text' => 'Oferta',
        'background_color' => '#dc2626',
    ]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'product_label.created']);
});

test('an invalid hex color is rejected', function () {
    $this->post(route('admin.product-labels.store'), labelPayload([
        'website_id' => $this->website->id,
        'background_color' => 'red',
    ]))->assertSessionHasErrors('background_color');
});

test('the text color is required', function () {
    $this->post(route('admin.product-labels.store'), labelPayload([
        'website_id' => $this->website->id,
        'text_color' => '',
    ]))->assertSessionHasErrors('text_color');
});

test('an admin can update a label', function () {
    $label = ProductLabel::factory()->create(['website_id' => $this->website->id]);

    $this->put(route('admin.product-labels.update', $label), labelPayload([
        'website_id' => $this->website->id,
        'text' => 'Actualizada',
    ]))->assertRedirect();

    expect($label->fresh()->text)->toBe('Actualizada');
});

test('an admin can delete a label', function () {
    $label = ProductLabel::factory()->create(['website_id' => $this->website->id]);

    $this->delete(route('admin.product-labels.destroy', $label))->assertRedirect();

    $this->assertDatabaseMissing('product_labels', ['id' => $label->id]);
});

test('the Soporte role cannot manage labels', function () {
    $support = User::factory()->create();
    $support->assignRole('Soporte');

    $this->actingAs($support)
        ->get(route('admin.product-labels.index'))
        ->assertForbidden();
});
