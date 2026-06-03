<?php

use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

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
