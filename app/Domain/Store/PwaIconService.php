<?php

namespace App\Domain\Store;

use App\Models\Media;
use App\Models\Store;
use App\Models\Website;
use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PwaIconService
{
    /** @var list<int> */
    public const SIZES = [180, 192, 512];

    /** @var list<string> */
    private const SUPPORTED_MIME_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    public function source(Store $store, Website $website): ?Media
    {
        $store->loadMissing('media');
        $website->loadMissing('media');

        $candidates = [
            $store->primaryMedia('pwa_icon'),
            $store->primaryMedia('logo'),
            $website->primaryMedia('favicon'),
            $website->primaryMedia('logo'),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate && $this->canRender($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function canRender(Media $media): bool
    {
        return $media->visibility === Media::VISIBILITY_PUBLIC
            && in_array($media->mime_type, self::SUPPORTED_MIME_TYPES, true)
            && Storage::disk($media->disk)->exists($media->path);
    }

    public function render(Media $media, int $size): string
    {
        if (! in_array($size, self::SIZES, true) || ! $this->canRender($media)) {
            throw new RuntimeException('No se puede generar el icono PWA solicitado.');
        }

        $source = imagecreatefromstring(Storage::disk($media->disk)->get($media->path));

        if (! $source instanceof GdImage) {
            throw new RuntimeException('El archivo de origen no es una imagen compatible con GD.');
        }

        $target = imagecreatetruecolor($size, $size);

        if (! $target instanceof GdImage) {
            imagedestroy($source);

            throw new RuntimeException('No se pudo preparar el icono PWA.');
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefill($target, 0, 0, $transparent);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min($size / $sourceWidth, $size / $sourceHeight);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        imagecopyresampled(
            $target,
            $source,
            (int) floor(($size - $targetWidth) / 2),
            (int) floor(($size - $targetHeight) / 2),
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );

        ob_start();
        imagepng($target, null, 9);
        $contents = ob_get_clean();

        imagedestroy($target);
        imagedestroy($source);

        if (! is_string($contents)) {
            throw new RuntimeException('No se pudo codificar el icono PWA.');
        }

        return $contents;
    }

    public function version(Media $media): string
    {
        return $media->id.'-'.($media->updated_at?->getTimestamp() ?? 0);
    }
}
