<?php

use App\Models\Media;
use App\Models\Product;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageSection;
use App\Models\User;
use App\Services\MediaUsageService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);
});

test('a super admin can view the media library', function () {
    $this->get(route('admin.media.index'))->assertOk();
});

test('a super admin can upload files', function () {
    Storage::fake('public');

    $this->post(route('admin.media.store'), [
        'files' => [UploadedFile::fake()->image('photo.jpg')],
        'visibility' => 'public',
    ])->assertRedirect();

    $this->assertDatabaseCount('media', 1);
});

test('a super admin can update media metadata', function () {
    $media = Media::factory()->create();

    $this->put(route('admin.media.update', $media), [
        'title' => 'Banner',
        'alt' => 'Texto alternativo',
    ])->assertRedirect();

    expect($media->fresh()->title)->toBe('Banner');
});

test('a super admin can delete media', function () {
    Storage::fake('public');
    $media = Media::factory()->create();
    Storage::disk('public')->put($media->path, 'x');

    $this->delete(route('admin.media.destroy', $media))->assertRedirect();
    $this->assertDatabaseMissing('media', ['id' => $media->id]);
});

test('a user without media permission is forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.media.index'))->assertForbidden();
});

test('a private file can be downloaded with a signed url', function () {
    Storage::fake('local');
    $media = Media::factory()->private()->create();
    Storage::disk('local')->put($media->path, 'secret');

    $this->get(URL::signedRoute('media.download', $media))->assertOk();
});

test('a private file rejects an unsigned download', function () {
    $media = Media::factory()->private()->create();

    $this->get(route('media.download', $media))->assertForbidden();
});

test('media usage service detects products sections seo and unused media', function () {
    $productMedia = Media::factory()->create(['name' => 'product.jpg']);
    $sectionMedia = Media::factory()->create(['name' => 'hero.jpg']);
    $seoMedia = Media::factory()->create(['name' => 'seo.jpg']);
    $unusedMedia = Media::factory()->create(['name' => 'unused.jpg']);

    $product = Product::factory()->create(['sku' => 'MEDIA-1', 'name' => 'Camilla']);
    $product->attachMedia($productMedia, 'gallery', isPrimary: true);

    $page = StorefrontPage::factory()->create(['title' => 'Landing']);
    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_HERO,
        'settings' => [
            'slides' => [
                ['title' => 'Principal', 'media_id' => $sectionMedia->id],
            ],
        ],
    ]);
    $page->stores()->updateExistingPivot($page->store_id, ['og_media_id' => $seoMedia->id]);

    $service = app(MediaUsageService::class);
    $usedIds = $service->usedMediaIds();
    $usages = $service->usagesFor(Media::whereIn('id', [$productMedia->id, $sectionMedia->id, $seoMedia->id, $unusedMedia->id])->get());

    expect($usedIds)->toContain($productMedia->id, $sectionMedia->id, $seoMedia->id)
        ->and($usedIds)->not->toContain($unusedMedia->id)
        ->and($usages[$productMedia->id][0]['context'])->toBe('products')
        ->and($usages[$sectionMedia->id][0]['context'])->toBe('sections')
        ->and($usages[$seoMedia->id][0]['context'])->toBe('seo')
        ->and($usages)->not->toHaveKey($unusedMedia->id);
});

test('media library can filter by name unused status and product context', function () {
    $productMedia = Media::factory()->create(['name' => 'catalog-product.jpg']);
    $unusedMedia = Media::factory()->create(['name' => 'orphan-banner.jpg']);
    Media::factory()->create(['name' => 'other.jpg']);

    $product = Product::factory()->create(['name' => 'Mesa']);
    $product->attachMedia($productMedia, 'gallery', isPrimary: true);

    $this->get(route('admin.media.index', ['name' => 'orphan', 'usage' => 'unused']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/media/index')
            ->where('filters.name', 'orphan')
            ->where('filters.usage', 'unused')
            ->has('media.data', 1)
            ->where('media.data.0.id', $unusedMedia->id)
        );

    $this->get(route('admin.media.index', ['context' => 'products']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.context', 'products')
            ->has('media.data', 1)
            ->where('media.data.0.id', $productMedia->id)
            ->where('media.data.0.usages.0.context', 'products')
        );
});
