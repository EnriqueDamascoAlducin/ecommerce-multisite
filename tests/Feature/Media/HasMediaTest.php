<?php

use App\Models\Media;
use App\Models\Website;

test('a model can sync a media collection with a primary item', function () {
    $website = Website::factory()->create();
    $first = Media::factory()->create();
    $second = Media::factory()->create();

    $website->syncMediaCollection([$first->id, $second->id], 'gallery');

    expect($website->mediaInCollection('gallery'))->toHaveCount(2)
        ->and($website->primaryMedia('gallery')->id)->toBe($first->id);
});

test('collections are isolated from each other', function () {
    $website = Website::factory()->create();
    $logo = Media::factory()->create();
    $gallery = Media::factory()->create();

    $website->attachMedia($logo, 'logo', isPrimary: true);
    $website->attachMedia($gallery, 'gallery');

    expect($website->mediaInCollection('logo'))->toHaveCount(1)
        ->and($website->mediaInCollection('gallery'))->toHaveCount(1)
        ->and($website->primaryMedia('logo')->id)->toBe($logo->id);
});

test('deleting media detaches it from models', function () {
    $website = Website::factory()->create();
    $media = Media::factory()->create();
    $website->attachMedia($media, 'gallery');

    $media->delete();

    expect($website->fresh()->mediaInCollection('gallery'))->toHaveCount(0);
});
