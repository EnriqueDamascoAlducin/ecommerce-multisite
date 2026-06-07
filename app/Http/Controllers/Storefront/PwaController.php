<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Store\StoreContext;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PwaController extends Controller
{
    public function __construct(private readonly StoreContext $context) {}

    public function manifest(): JsonResponse
    {
        $website = $this->context->website();
        $store = $this->context->store();
        $pathPrefix = trim($this->context->pathPrefix(), '/');
        $startUrl = $pathPrefix === '' ? '/' : "/{$pathPrefix}/";
        $icon = $this->icon($website);

        $icons = $icon ? [
            ['src' => $this->absoluteUrl($this->versionedMediaUrl($icon)), 'sizes' => '192x192', 'type' => $icon->mime_type, 'purpose' => 'any maskable'],
            ['src' => $this->absoluteUrl($this->versionedMediaUrl($icon)), 'sizes' => '512x512', 'type' => $icon->mime_type, 'purpose' => 'any maskable'],
        ] : [];

        return response()
            ->json([
                'name' => $website?->name ?? config('app.name'),
                'short_name' => $store?->name ?? $website?->name ?? config('app.name'),
                'description' => "Tienda {$website?->name}",
                'start_url' => $startUrl,
                'scope' => $startUrl,
                'display' => 'standalone',
                'orientation' => 'portrait-primary',
                'background_color' => '#ffffff',
                'theme_color' => '#991b1b',
                'icons' => $icons,
            ])
            ->header('Content-Type', 'application/manifest+json');
    }

    public function serviceWorker(): Response
    {
        $script = <<<'JS'
const CACHE_NAME = 'ecommerce-multisite-pwa-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/admin')) {
        return;
    }

    event.respondWith(
        fetch(request)
            .then((response) => {
                if (response.ok && response.type === 'basic') {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                }

                return response;
            })
            .catch(() => caches.match(request)),
    );
});
JS;

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    private function icon(?Website $website): ?Media
    {
        if (! $website) {
            return null;
        }

        $website->loadMissing('media');

        return $website->primaryMedia('favicon') ?? $website->primaryMedia('logo');
    }

    private function versionedMediaUrl(Media $media): string
    {
        $separator = str_contains($media->url, '?') ? '&' : '?';

        return $media->url.$separator.'v='.$media->updated_at?->getTimestamp();
    }

    private function absoluteUrl(string $url): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return url($url);
    }
}
