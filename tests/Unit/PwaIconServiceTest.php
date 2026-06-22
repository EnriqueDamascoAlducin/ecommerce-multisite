<?php

use App\Domain\Store\PwaIconService;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('it generates real square png installation icons with gd', function (int $size) {
    Storage::fake('public');

    $source = imagecreatetruecolor(640, 320);
    $color = imagecolorallocate($source, 153, 27, 27);
    imagefill($source, 0, 0, $color);
    ob_start();
    imagepng($source);
    $sourceContents = ob_get_clean();
    imagedestroy($source);

    expect($sourceContents)->toBeString();

    Storage::disk('public')->put('pwa-test/source.png', $sourceContents);

    $media = new Media([
        'disk' => 'public',
        'directory' => 'pwa-test',
        'filename' => 'source.png',
        'name' => 'source.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'size' => strlen($sourceContents),
        'is_image' => true,
        'visibility' => Media::VISIBILITY_PUBLIC,
    ]);

    $contents = app(PwaIconService::class)->render($media, $size);
    $dimensions = getimagesizefromstring($contents);

    expect($contents)->toStartWith("\x89PNG")
        ->and($dimensions)->not->toBeFalse()
        ->and($dimensions[0])->toBe($size)
        ->and($dimensions[1])->toBe($size);
})->with(PwaIconService::SIZES);
