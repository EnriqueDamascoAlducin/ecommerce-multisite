<?php

use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->service = app(MediaService::class);
});

test('stores a public image on the public disk', function () {
    Storage::fake('public');

    $media = $this->service->store(UploadedFile::fake()->image('photo.jpg', 120, 120), 'gallery', 'public');

    expect($media->is_image)->toBeTrue()
        ->and($media->disk)->toBe('public')
        ->and($media->visibility)->toBe('public');
    Storage::disk('public')->assertExists($media->path);
});

test('stores a private document on the local disk', function () {
    Storage::fake('local');

    $media = $this->service->store(
        UploadedFile::fake()->create('manual.pdf', 50, 'application/pdf'),
        'downloads',
        'private',
    );

    expect($media->visibility)->toBe('private')
        ->and($media->disk)->toBe('local')
        ->and($media->is_image)->toBeFalse();
    Storage::disk('local')->assertExists($media->path);
});

test('deleting media removes the file and the record', function () {
    Storage::fake('public');

    $media = $this->service->store(UploadedFile::fake()->image('a.jpg'), 'default', 'public');
    $path = $media->path;

    $this->service->delete($media);

    Storage::disk('public')->assertMissing($path);
    $this->assertDatabaseMissing('media', ['id' => $media->id]);
});

test('a private media exposes a signed download url', function () {
    $media = Media::factory()->private()->create();

    expect($media->url)->toContain('/media/'.$media->id.'/download')
        ->and($media->url)->toContain('signature=');
});
