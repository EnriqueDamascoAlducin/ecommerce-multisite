<?php

use App\Models\Media;
use App\Models\Store;
use App\Models\User;
use App\Models\Website;
use App\Services\MediaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);
});

test('uploading a logo file attaches it to the website logo collection', function () {
    Storage::fake('public');
    $website = Website::factory()->create();

    $this->put(route('admin.websites.update', $website), [
        'code' => $website->code,
        'name' => $website->name,
        'sort_order' => $website->sort_order,
        'logo_file' => UploadedFile::fake()->image('logo.png'),
    ])->assertRedirect(route('admin.websites.index'));

    $logo = $website->fresh()->primaryMedia('logo');
    expect($logo)->not->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'media.uploaded']);
});

test('choosing an existing media from the library sets it as the logo', function () {
    $website = Website::factory()->create();
    $media = Media::factory()->create();

    $this->put(route('admin.websites.update', $website), [
        'code' => $website->code,
        'name' => $website->name,
        'sort_order' => $website->sort_order,
        'logo_media_id' => $media->id,
    ])->assertRedirect();

    expect($website->fresh()->primaryMedia('logo')?->id)->toBe($media->id);
});

test('choosing an existing media from the library sets it as the favicon', function () {
    $website = Website::factory()->create();
    $media = Media::factory()->create([
        'filename' => 'favicon.png',
        'name' => 'favicon.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
    ]);

    $this->put(route('admin.websites.update', $website), [
        'code' => $website->code,
        'name' => $website->name,
        'sort_order' => $website->sort_order,
        'favicon_media_id' => $media->id,
    ])->assertRedirect();

    expect($website->fresh()->primaryMedia('favicon')?->id)->toBe($media->id);
});

test('removing the logo empties the logo collection', function () {
    $website = Website::factory()->create();
    $media = Media::factory()->create();
    $website->syncMediaCollection([$media->id], 'logo');

    $this->put(route('admin.websites.update', $website), [
        'code' => $website->code,
        'name' => $website->name,
        'sort_order' => $website->sort_order,
        'remove_logo' => '1',
    ])->assertRedirect();

    expect($website->fresh()->primaryMedia('logo'))->toBeNull();
});

test('an invalid logo file is rejected', function () {
    $website = Website::factory()->create();

    $this->put(route('admin.websites.update', $website), [
        'code' => $website->code,
        'name' => $website->name,
        'sort_order' => $website->sort_order,
        'logo_file' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('logo_file');
});

test('a user without store settings permission cannot edit websites', function () {
    $this->actingAs(User::factory()->create());

    $website = Website::factory()->create();

    $this->get(route('admin.websites.edit', $website))->assertForbidden();
});

test('the storefront shares the website logo url', function () {
    $website = Website::factory()->create(['is_default' => true]);
    Store::factory()->create([
        'website_id' => $website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    $media = Media::factory()->create();
    $website->syncMediaCollection([$media->id], 'logo');

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('store.website.logo_url', $media->fresh()->url.'?v='.$media->fresh()->updated_at?->getTimestamp()));
});

test('the storefront shares the website favicon url', function () {
    $website = Website::factory()->create(['is_default' => true]);
    Store::factory()->create([
        'website_id' => $website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    $media = Media::factory()->create([
        'filename' => 'favicon.png',
        'name' => 'favicon.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
    ]);
    $website->syncMediaCollection([$media->id], 'favicon');

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('store.website.favicon_url', $media->fresh()->url.'?v='.$media->fresh()->updated_at?->getTimestamp()));
});

test('the pwa manifest uses the store name and falls back to the website favicon', function () {
    Storage::fake('public');
    $website = Website::factory()->create(['is_default' => true, 'name' => 'Equipos Interferenciales']);
    Store::factory()->create([
        'website_id' => $website->id,
        'is_default' => true,
        'is_active' => true,
        'name' => 'Tienda Principal',
    ]);
    $media = app(MediaService::class)->store(UploadedFile::fake()->image('favicon.png', 512, 512), 'test');
    $website->syncMediaCollection([$media->id], 'favicon');

    $this->get(route('storefront.pwa.manifest'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('name', 'Tienda Principal')
        ->assertJsonPath('short_name', 'Tienda Principal')
        ->assertJsonPath('id', '/')
        ->assertJsonPath('icons.0.src', url("/pwa-icon/192.png?v={$media->id}-{$media->updated_at->getTimestamp()}"))
        ->assertJsonPath('icons.0.type', 'image/png')
        ->assertJsonPath('display', 'standalone');
});
