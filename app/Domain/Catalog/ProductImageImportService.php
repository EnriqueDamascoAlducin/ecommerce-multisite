<?php

namespace App\Domain\Catalog;

use App\Models\Media;
use App\Models\Product;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ProductImageImportService
{
    private const MAX_IMAGE_BYTES = 10 * 1024 * 1024;

    public function __construct(private readonly MediaService $mediaService) {}

    /**
     * @param  list<array{path: string, label: string|null, primary: bool}>  $images
     * @param  list<string>  $baseUrls
     * @return array{downloaded: int, reused: int, failed: int, warnings: list<string>}
     */
    public function import(Product $product, array $images, array $baseUrls, ?User $user = null): array
    {
        $result = ['downloaded' => 0, 'reused' => 0, 'failed' => 0, 'warnings' => []];
        $existing = $product->media()
            ->wherePivot('collection', 'gallery')
            ->get()
            ->keyBy(fn (Media $media): string => Str::lower($media->name));
        $nextSortOrder = $existing->count();

        foreach ($images as $image) {
            $name = basename($image['path']);

            if ($name === '' || $name === '.' || $name === 'no_selection') {
                continue;
            }

            $existingMedia = $existing->get(Str::lower($name));

            if ($existingMedia) {
                $result['reused']++;

                if ($image['primary']) {
                    $this->markPrimary($product, $existingMedia);
                }

                continue;
            }

            $download = $this->download($image['path'], $baseUrls);

            if (! $download['contents']) {
                $result['failed']++;
                $result['warnings'][] = "No se pudo descargar {$image['path']}.";

                continue;
            }

            $media = $this->storeDownloadedImage(
                $download['contents'],
                $name,
                $download['mime_type'],
                $image['label'] ?: $product->name,
                $user,
            );

            if (! $media) {
                $result['failed']++;
                $result['warnings'][] = "No se pudo guardar {$image['path']}.";

                continue;
            }

            $product->attachMedia($media, 'gallery', $image['primary'], $nextSortOrder);
            $existing->put(Str::lower($name), $media);
            $nextSortOrder++;
            $result['downloaded']++;

            if ($image['primary']) {
                $this->markPrimary($product, $media);
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $baseUrls
     * @return array{contents: string|null, mime_type: string|null}
     */
    private function download(string $path, array $baseUrls): array
    {
        $path = $this->safePath($path);

        if ($path === null) {
            return ['contents' => null, 'mime_type' => null];
        }

        foreach (array_unique($baseUrls) as $baseUrl) {
            $url = rtrim($baseUrl, '/').'/'.ltrim($path, '/');

            try {
                $request = Http::accept('image/*')
                    ->connectTimeout(5)
                    ->timeout(20)
                    ->retry(2, 250, throw: false);

                if (app()->isLocal()) {
                    $request->withoutVerifying();
                }

                $response = $request->get($url);
            } catch (ConnectionException) {
                continue;
            }

            $contents = $response->body();
            $mimeType = Str::before((string) $response->header('Content-Type'), ';');

            if ($response->successful()
                && str_starts_with($mimeType, 'image/')
                && strlen($contents) > 0
                && strlen($contents) <= self::MAX_IMAGE_BYTES) {
                return ['contents' => $contents, 'mime_type' => $mimeType];
            }
        }

        return ['contents' => null, 'mime_type' => null];
    }

    private function safePath(string $path): ?string
    {
        $path = trim($path);

        if ($path === '' || $path === 'no_selection' || str_contains($path, '..')) {
            return null;
        }

        return ltrim($path, '/');
    }

    private function storeDownloadedImage(
        string $contents,
        string $name,
        string $mimeType,
        string $alt,
        ?User $user,
    ): ?Media {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'catalog-image-');

        if ($temporaryPath === false) {
            return null;
        }

        try {
            if (file_put_contents($temporaryPath, $contents) === false) {
                return null;
            }

            $file = new UploadedFile($temporaryPath, $name, $mimeType, null, true);
            $media = $this->mediaService->store(
                $file,
                'gallery',
                Media::VISIBILITY_PUBLIC,
                $user?->id,
            );
            $media->update(['alt' => $alt]);

            return $media;
        } catch (Throwable) {
            return null;
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function markPrimary(Product $product, Media $media): void
    {
        $product->media()->newPivotStatement()
            ->where('mediable_type', $product->getMorphClass())
            ->where('mediable_id', $product->getKey())
            ->where('collection', 'gallery')
            ->update(['is_primary' => false]);

        $product->media()->updateExistingPivot($media->getKey(), ['is_primary' => true]);
    }
}
