<?php

use App\Models\Attribute;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);
});

test('a super admin can list attributes', function () {
    $this->get(route('admin.attributes.index'))->assertOk();
});

test('a super admin can create a text attribute', function () {
    $this->post(route('admin.attributes.store'), [
        'code' => 'material',
        'name' => 'Material',
        'type' => 'text',
        'is_visible' => '1',
    ])->assertRedirect(route('admin.attributes.index'));

    $this->assertDatabaseHas('attributes', ['code' => 'material', 'type' => 'text']);
});

test('a select attribute persists its options', function () {
    $this->post(route('admin.attributes.store'), [
        'code' => 'color',
        'name' => 'Color',
        'type' => 'select',
        'options' => [
            ['label' => 'Rojo', 'value' => 'rojo'],
            ['label' => 'Azul', 'value' => ''],
        ],
    ])->assertRedirect();

    $attribute = Attribute::where('code', 'color')->firstOrFail();

    expect($attribute->options)->toHaveCount(2);
    $this->assertDatabaseHas('attribute_options', ['attribute_id' => $attribute->id, 'value' => 'rojo']);
    $this->assertDatabaseHas('attribute_options', ['attribute_id' => $attribute->id, 'value' => 'azul']);
});

test('options are discarded for non-option attribute types', function () {
    $this->post(route('admin.attributes.store'), [
        'code' => 'peso',
        'name' => 'Peso',
        'type' => 'number',
        'options' => [
            ['label' => 'No debería guardarse', 'value' => 'x'],
        ],
    ])->assertRedirect();

    $attribute = Attribute::where('code', 'peso')->firstOrFail();
    expect($attribute->options)->toHaveCount(0);
});

test('the code must be unique', function () {
    Attribute::factory()->create(['code' => 'dup']);

    $this->post(route('admin.attributes.store'), [
        'code' => 'dup',
        'name' => 'Otro',
        'type' => 'text',
    ])->assertSessionHasErrors('code');
});

test('the code format is validated', function () {
    $this->post(route('admin.attributes.store'), [
        'code' => 'Invalid Code',
        'name' => 'X',
        'type' => 'text',
    ])->assertSessionHasErrors('code');
});

test('the type must be one of the allowed types', function () {
    $this->post(route('admin.attributes.store'), [
        'code' => 'bad_type',
        'name' => 'X',
        'type' => 'rich-text',
    ])->assertSessionHasErrors('type');
});

test('updating to a non-option type clears existing options', function () {
    $attribute = Attribute::factory()->select()->create(['code' => 'switchme']);
    $attribute->options()->create(['label' => 'A', 'value' => 'a', 'sort_order' => 0]);

    $this->put(route('admin.attributes.update', $attribute), [
        'code' => 'switchme',
        'name' => $attribute->name,
        'type' => 'text',
    ])->assertRedirect();

    expect($attribute->fresh()->options)->toHaveCount(0);
});

test('a user without catalog attribute permission is forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.attributes.index'))->assertForbidden();
});
