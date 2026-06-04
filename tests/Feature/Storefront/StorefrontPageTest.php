<?php

use App\Models\Store;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageSection;
use App\Models\Website;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true]);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
});

test('home renders published cms sections for the resolved store', function () {
    $page = StorefrontPage::factory()->create(['store_id' => $this->store->id]);
    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_HERO,
        'settings' => ['title' => 'Hot Days 2024'],
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/home')
            ->where('contentPage.sections.0.settings.title', 'Hot Days 2024'));
});

test('home falls back when no cms page exists', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/home')
            ->where('contentPage', null));
});

test('inactive sections are not rendered', function () {
    $page = StorefrontPage::factory()->create(['store_id' => $this->store->id]);
    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'is_active' => false,
        'settings' => ['title' => 'Hidden'],
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('contentPage.sections', 0));
});

test('published cms page renders by slug', function () {
    $page = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => 'nosotros',
        'title' => 'Nosotros',
        'is_published' => true,
    ]);
    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_TEXT_IMAGE,
        'settings' => ['title' => 'Equipo experto'],
    ]);

    $this->get('/nosotros')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/home')
            ->where('contentPage.slug', 'nosotros')
            ->where('contentPage.sections.0.settings.title', 'Equipo experto'));
});

test('unpublished cms page returns not found', function () {
    StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => 'oculta',
        'is_published' => false,
    ]);

    $this->get('/oculta')->assertNotFound();
});

test('category route still wins over cms catch all route', function () {
    $this->get('/c/no-existe')->assertNotFound();
});

test('inquiry form stores leads for the resolved store', function () {
    $this->post(route('storefront.inquiries.store'), [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'interest_area' => 'Electrotherapy Equipment',
        'message' => 'Need a quote.',
    ])->assertRedirect();

    $this->assertDatabaseHas('store_inquiries', [
        'store_id' => $this->store->id,
        'email' => 'john@example.com',
        'status' => 'new',
    ]);
});
