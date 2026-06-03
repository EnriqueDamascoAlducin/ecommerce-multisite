<?php

use App\Models\User;

test('the storefront home is publicly accessible', function () {
    $this->get(route('home'))->assertOk();
});

test('guests are redirected from the admin to the login page', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can visit the admin dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.dashboard'))->assertOk();
});
