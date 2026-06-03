<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);
});

test('a super admin can list roles', function () {
    $this->get(route('admin.roles.index'))->assertOk();
});

test('a super admin can list permissions', function () {
    $this->get(route('admin.permissions.index'))->assertOk();
});

test('a super admin can create a role with permissions', function () {
    $this->post(route('admin.roles.store'), [
        'name' => 'Editor de catálogo',
        'permissions' => ['catalog.products.view', 'catalog.products.edit'],
    ])->assertRedirect(route('admin.roles.index'));

    $role = Role::findByName('Editor de catálogo');
    expect($role->hasPermissionTo('catalog.products.edit'))->toBeTrue();
    expect($role->hasPermissionTo('catalog.products.delete'))->toBeFalse();
});

test('a super admin can update a role', function () {
    $role = Role::create(['name' => 'Temporal', 'guard_name' => 'web']);

    $this->put(route('admin.roles.update', $role), [
        'name' => 'Temporal Renombrado',
        'permissions' => ['inventory.view'],
    ])->assertRedirect(route('admin.roles.index'));

    $role->refresh();
    expect($role->name)->toBe('Temporal Renombrado');
    expect($role->hasPermissionTo('inventory.view'))->toBeTrue();
});

test('the Super Admin role cannot be deleted', function () {
    $role = Role::findByName('Super Admin');

    $this->delete(route('admin.roles.destroy', $role));
    $this->assertDatabaseHas('roles', ['name' => 'Super Admin']);
});

test('a non super admin role can be deleted', function () {
    $role = Role::create(['name' => 'Descartable', 'guard_name' => 'web']);

    $this->delete(route('admin.roles.destroy', $role))->assertRedirect(route('admin.roles.index'));
    $this->assertDatabaseMissing('roles', ['name' => 'Descartable']);
});

test('a user without role permissions is forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.roles.index'))->assertForbidden();
});
