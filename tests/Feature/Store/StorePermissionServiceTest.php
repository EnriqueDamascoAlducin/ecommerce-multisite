<?php

use App\Domain\Store\StorePermissionService;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->service = app(StorePermissionService::class);
});

test('a super admin can manage every store', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $store = Store::factory()->create();

    expect($this->service->canManageStore($admin, $store))->toBeTrue();
    expect($this->service->manageableStores($admin))->toHaveCount(1);
});

test('a regular admin can only manage assigned stores', function () {
    $user = User::factory()->create();
    $assigned = Store::factory()->create();
    $other = Store::factory()->create();

    $user->stores()->attach($assigned);

    expect($this->service->canManageStore($user, $assigned))->toBeTrue();
    expect($this->service->canManageStore($user, $other))->toBeFalse();
    expect($this->service->manageableStores($user)->pluck('id'))
        ->toContain($assigned->id)
        ->not->toContain($other->id);
});
