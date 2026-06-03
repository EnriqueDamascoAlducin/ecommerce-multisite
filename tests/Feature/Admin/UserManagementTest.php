<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function superAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    return $admin;
}

test('a super admin can list users', function () {
    $this->actingAs(superAdmin());

    $this->get(route('admin.users.index'))->assertOk();
});

test('a user without permission cannot access user management', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.users.index'))->assertForbidden();
});

test('a super admin can create a user with roles', function () {
    $this->actingAs(superAdmin());

    $response = $this->post(route('admin.users.store'), [
        'name' => 'Nuevo Usuario',
        'email' => 'nuevo@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'roles' => ['Ventas'],
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $this->assertDatabaseHas('users', ['email' => 'nuevo@example.com']);

    $user = User::where('email', 'nuevo@example.com')->firstOrFail();
    expect($user->hasRole('Ventas'))->toBeTrue();

    $this->assertDatabaseHas('audit_logs', ['action' => 'user.created']);
});

test('creating a user requires a unique email', function () {
    $this->actingAs(superAdmin());

    User::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('admin.users.store'), [
        'name' => 'Dup',
        'email' => 'taken@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');
});

test('a super admin can update a user and sync roles', function () {
    $this->actingAs(superAdmin());

    $user = User::factory()->create();
    $user->assignRole('Soporte');

    $this->put(route('admin.users.update', $user), [
        'name' => 'Actualizado',
        'email' => $user->email,
        'roles' => ['Ventas'],
    ])->assertRedirect(route('admin.users.index'));

    $user->refresh();
    expect($user->name)->toBe('Actualizado');
    expect($user->hasRole('Ventas'))->toBeTrue();
    expect($user->hasRole('Soporte'))->toBeFalse();
});

test('a super admin can delete another user', function () {
    $this->actingAs(superAdmin());

    $user = User::factory()->create();

    $this->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('a user cannot delete their own account', function () {
    $admin = superAdmin();
    $this->actingAs($admin);

    $this->delete(route('admin.users.destroy', $admin));
    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});
