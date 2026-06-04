<?php

use App\Models\Media;
use App\Models\Store;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageSection;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);
});

test('an admin can list storefront pages', function () {
    $store = Store::factory()->create();

    $this->get(route('admin.storefront.pages.index', ['store_id' => $store->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/storefront/pages/index')
            ->where('currentStoreId', $store->id)
            ->has('pages'));
});

test('an admin can create a storefront page', function () {
    $store = Store::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Nosotros',
        'slug' => 'nosotros',
        'is_published' => true,
    ])->assertRedirect();

    $this->assertDatabaseHas('storefront_pages', [
        'store_id' => $store->id,
        'slug' => 'nosotros',
        'title' => 'Nosotros',
    ]);
});

test('an admin can create and update a page section with media', function () {
    $store = Store::factory()->create();
    $page = StorefrontPage::factory()->create(['store_id' => $store->id]);
    $media = Media::factory()->create();

    $this->post(route('admin.storefront.pages.sections.store', $page), [
        'store_id' => $store->id,
        'type' => StorefrontPageSection::TYPE_HERO,
        'is_active' => true,
        'settings' => [
            'title' => 'Hot Days 2024',
            'media_id' => $media->id,
        ],
    ])->assertRedirect();

    $section = $page->sections()->firstOrFail();

    $this->put(route('admin.storefront.pages.sections.update', [$page, $section]), [
        'store_id' => $store->id,
        'type' => StorefrontPageSection::TYPE_HERO,
        'is_active' => false,
        'settings' => [
            'title' => 'Updated',
            'media_id' => $media->id,
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('storefront_page_sections', [
        'id' => $section->id,
        'is_active' => false,
    ]);
});

test('an admin can reorder sections', function () {
    $page = StorefrontPage::factory()->create();
    $first = StorefrontPageSection::factory()->create(['storefront_page_id' => $page->id, 'sort_order' => 0]);
    $second = StorefrontPageSection::factory()->create(['storefront_page_id' => $page->id, 'sort_order' => 1]);

    $this->post(route('admin.storefront.pages.sections.reorder', $page), [
        'sections' => [
            ['id' => $first->id, 'sort_order' => 1],
            ['id' => $second->id, 'sort_order' => 0],
        ],
    ])->assertRedirect();

    expect($first->fresh()->sort_order)->toBe(1);
    expect($second->fresh()->sort_order)->toBe(0);
});

test('a user without storefront permission is forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.storefront.pages.index'))->assertForbidden();
});

test('home page cannot be deleted', function () {
    $page = StorefrontPage::factory()->create(['slug' => StorefrontPage::HOME]);

    $this->delete(route('admin.storefront.pages.destroy', $page))->assertStatus(422);
});
