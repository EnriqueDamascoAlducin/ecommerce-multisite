<?php

use App\Models\Media;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\Website;
use App\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function pwaMedia(string $name, int $size = 512): Media
{
    return app(MediaService::class)->store(
        UploadedFile::fake()->image($name, $size, $size),
        'pwa-test',
    );
}

test('the manifest and png icons use the store identity resolved by domain', function () {
    $website = Website::factory()->create(['is_default' => true, 'name' => 'Website']);
    $store = Store::factory()->for($website)->create([
        'code' => 'main',
        'name' => 'Tienda Norte',
        'is_default' => true,
        'is_active' => true,
    ]);
    StoreDomain::factory()->for($store)->create(['host' => 'norte.test']);
    $icon = pwaMedia('norte.png');
    $store->syncMediaCollection([$icon->id], 'pwa_icon');

    $version = "{$icon->id}-{$icon->updated_at->getTimestamp()}";

    $this->get('http://norte.test/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('name', 'Tienda Norte')
        ->assertJsonPath('short_name', 'Tienda Norte')
        ->assertJsonPath('description', 'Tienda Norte')
        ->assertJsonPath('id', '/')
        ->assertJsonPath('start_url', '/')
        ->assertJsonPath('scope', '/')
        ->assertJsonPath('icons.0.src', "http://norte.test/pwa-icon/192.png?v={$version}")
        ->assertJsonPath('icons.0.sizes', '192x192')
        ->assertJsonPath('icons.1.src', "http://norte.test/pwa-icon/512.png?v={$version}");

    $response = $this->get("http://norte.test/pwa-icon/192.png?v={$version}")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'public, max-age=31536000, immutable');

    $dimensions = getimagesizefromstring($response->getContent());

    expect($dimensions)->not->toBeFalse()
        ->and($dimensions[0])->toBe(192)
        ->and($dimensions[1])->toBe(192);
});

test('a prefixed store receives stable scoped urls and its own identity', function () {
    $website = Website::factory()->create(['is_default' => true]);
    $main = Store::factory()->for($website)->create([
        'code' => 'main',
        'is_default' => true,
        'is_active' => true,
    ]);
    StoreDomain::factory()->for($main)->create(['host' => 'catalogo.test']);
    $sports = Store::factory()->for($website)->create([
        'code' => 'sports',
        'name' => 'Tienda Sports',
        'is_active' => true,
    ]);
    $icon = pwaMedia('sports.png');
    $sports->syncMediaCollection([$icon->id], 'pwa_icon');

    $this->get('http://catalogo.test/sports/manifest.webmanifest')
        ->assertOk()
        ->assertJsonPath('name', 'Tienda Sports')
        ->assertJsonPath('id', '/sports/')
        ->assertJsonPath('start_url', '/sports/')
        ->assertJsonPath('scope', '/sports/')
        ->assertJsonPath('icons.0.src', "http://catalogo.test/sports/pwa-icon/192.png?v={$icon->id}-{$icon->updated_at->getTimestamp()}");
});

test('the icon source falls back from store pwa icon to store logo and website media', function () {
    $website = Website::factory()->create(['is_default' => true]);
    $store = Store::factory()->for($website)->create([
        'is_default' => true,
        'is_active' => true,
    ]);
    $websiteLogo = pwaMedia('website-logo.png');
    $favicon = pwaMedia('favicon.png');
    $storeLogo = pwaMedia('store-logo.png');
    $pwaIcon = pwaMedia('install.png');
    $website->syncMediaCollection([$websiteLogo->id], 'logo');
    $website->syncMediaCollection([$favicon->id], 'favicon');
    $store->syncMediaCollection([$storeLogo->id], 'logo');

    $this->get('/manifest.webmanifest')
        ->assertJsonPath('icons.0.src', url("/pwa-icon/192.png?v={$storeLogo->id}-{$storeLogo->updated_at->getTimestamp()}"));

    $store->syncMediaCollection([$pwaIcon->id], 'pwa_icon');
    $this->get('/manifest.webmanifest')
        ->assertJsonPath('icons.0.src', url("/pwa-icon/192.png?v={$pwaIcon->id}-{$pwaIcon->updated_at->getTimestamp()}"));

    $store->syncMediaCollection([], 'pwa_icon');
    $store->syncMediaCollection([], 'logo');
    $this->get('/manifest.webmanifest')
        ->assertJsonPath('icons.0.src', url("/pwa-icon/192.png?v={$favicon->id}-{$favicon->updated_at->getTimestamp()}"));

    $website->syncMediaCollection([], 'favicon');
    $this->get('/manifest.webmanifest')
        ->assertJsonPath('icons.0.src', url("/pwa-icon/192.png?v={$websiteLogo->id}-{$websiteLogo->updated_at->getTimestamp()}"));
});

test('the storefront shares the apple installation icon for the resolved store', function () {
    $website = Website::factory()->create(['is_default' => true]);
    $store = Store::factory()->for($website)->create([
        'is_default' => true,
        'is_active' => true,
    ]);
    $icon = pwaMedia('apple.png');
    $store->syncMediaCollection([$icon->id], 'pwa_icon');

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('store.store.name', $store->name)
            ->where('store.pwa.apple_touch_icon_url', "/pwa-icon/180.png?v={$icon->id}-{$icon->updated_at->getTimestamp()}"));
});
