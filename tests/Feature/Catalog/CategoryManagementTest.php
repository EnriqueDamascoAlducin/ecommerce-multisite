<?php

use App\Models\Category;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);

    $this->website = Website::factory()->create();
});

test('a super admin can list categories of a website', function () {
    Category::factory()->create(['website_id' => $this->website->id]);

    $this->get(route('admin.categories.index', ['website_id' => $this->website->id]))->assertOk();
});

test('a super admin can create a root category', function () {
    $this->post(route('admin.categories.store'), [
        'website_id' => $this->website->id,
        'name' => 'Electrónica',
        'is_active' => '1',
    ])->assertRedirect();

    $this->assertDatabaseHas('categories', [
        'website_id' => $this->website->id,
        'name' => 'Electrónica',
        'slug' => 'electronica',
        'parent_id' => null,
    ]);
});

test('a category can be nested under a parent', function () {
    $parent = Category::factory()->create(['website_id' => $this->website->id]);

    $this->post(route('admin.categories.store'), [
        'website_id' => $this->website->id,
        'parent_id' => $parent->id,
        'name' => 'Subcategoría',
    ])->assertRedirect();

    $this->assertDatabaseHas('categories', [
        'name' => 'Subcategoría',
        'parent_id' => $parent->id,
    ]);
});

test('the slug is unique per website', function () {
    Category::factory()->create(['website_id' => $this->website->id, 'slug' => 'ropa', 'name' => 'Ropa']);

    $this->post(route('admin.categories.store'), [
        'website_id' => $this->website->id,
        'name' => 'Ropa',
    ])->assertRedirect();

    expect(Category::where('website_id', $this->website->id)->where('name', 'Ropa')->count())->toBe(2);
    expect(Category::where('slug', 'ropa-2')->exists())->toBeTrue();
});

test('the parent must belong to the same website', function () {
    $otherWebsite = Website::factory()->create();
    $foreignParent = Category::factory()->create(['website_id' => $otherWebsite->id]);

    $this->post(route('admin.categories.store'), [
        'website_id' => $this->website->id,
        'parent_id' => $foreignParent->id,
        'name' => 'Inválida',
    ])->assertSessionHasErrors('parent_id');
});

test('a category cannot be its own parent', function () {
    $category = Category::factory()->create(['website_id' => $this->website->id]);

    $this->put(route('admin.categories.update', $category), [
        'website_id' => $this->website->id,
        'parent_id' => $category->id,
        'name' => $category->name,
    ])->assertSessionHasErrors('parent_id');
});

test('a super admin can delete a category', function () {
    $category = Category::factory()->create(['website_id' => $this->website->id]);

    $this->delete(route('admin.categories.destroy', $category))->assertRedirect();
    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('a user without catalog category permission is forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.categories.index'))->assertForbidden();
});
