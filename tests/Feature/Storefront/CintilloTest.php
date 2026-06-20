<?php

use App\Models\InventorySource;
use App\Models\Store;
use App\Models\Website;
use App\Models\WebsiteHeaderSettings;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true]);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    InventorySource::factory()->default()->create();
});

test('a disabled cintillo is exposed as disabled', function () {
    WebsiteHeaderSettings::factory()->disabled()->create(['website_id' => $this->website->id]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('store.header.cintillo.enabled', false));
});

test('a text block exposes its text and colors', function () {
    WebsiteHeaderSettings::factory()->create([
        'website_id' => $this->website->id,
        'cintillo_blocks' => [['type' => 'text', 'text' => 'Hola mundo']],
        'cintillo_background_color' => '#dc2626',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('store.header.cintillo.enabled', true)
            ->where('store.header.cintillo.show_on_mobile', true)
            ->where('store.header.cintillo.blocks.0.type', 'text')
            ->where('store.header.cintillo.blocks.0.text', 'Hola mundo')
            ->where('store.header.cintillo.background_color', '#dc2626'));
});

test('mixed blocks expose text and social', function () {
    WebsiteHeaderSettings::factory()->mixed()->create(['website_id' => $this->website->id]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('store.header.cintillo.blocks', 2)
            ->where('store.header.cintillo.blocks.0.type', 'text')
            ->where('store.header.cintillo.blocks.1.type', 'social')
            ->where('store.header.cintillo.blocks.1.social.0.platform', 'facebook'));
});

test('an image block exposes its linked images', function () {
    WebsiteHeaderSettings::factory()->image()->create(['website_id' => $this->website->id]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('store.header.cintillo.blocks.0.type', 'image')
            ->has('store.header.cintillo.blocks.0.images', 2)
            ->where('store.header.cintillo.blocks.0.images.0.url', 'https://cdn.example.com/promo.png')
            ->where('store.header.cintillo.blocks.0.images.1.link', 'https://veterinaria.example.com'));
});

test('the mobile flag is exposed', function () {
    WebsiteHeaderSettings::factory()->create([
        'website_id' => $this->website->id,
        'cintillo_show_on_mobile' => false,
    ]);

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page->where('store.header.cintillo.show_on_mobile', false));
});

test('a website without settings defaults to an empty, mobile-visible cintillo', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('store.header.cintillo.enabled', false)
            ->where('store.header.cintillo.show_on_mobile', true)
            ->has('store.header.cintillo.blocks', 0));
});

test('header and menu colors are exposed, null when not customized', function () {
    WebsiteHeaderSettings::factory()->create([
        'website_id' => $this->website->id,
        'header_background_color' => '#0ea5e9',
        'menu_text_color' => '#ffffff',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('store.header.colors.header_background_color', '#0ea5e9')
            ->where('store.header.colors.menu_text_color', '#ffffff')
            ->where('store.header.colors.header_text_color', null)
            ->where('store.header.colors.menu_background_color', null));
});
