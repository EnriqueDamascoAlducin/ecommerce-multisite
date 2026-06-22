<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Store\PwaIconService;
use App\Domain\Store\StoreContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

class PwaController extends Controller
{
    public function __construct(
        private readonly StoreContext $context,
        private readonly PwaIconService $pwaIconService,
    ) {}

    public function manifest(): JsonResponse
    {
        $website = $this->context->website();
        $store = $this->context->store();
        $pathPrefix = trim($this->context->pathPrefix(), '/');
        $startUrl = $pathPrefix === '' ? '/' : "/{$pathPrefix}/";
        $icon = $store && $website ? $this->pwaIconService->source($store, $website) : null;
        $name = $store?->name ?? $website?->name ?? config('app.name');
        $iconBase = $pathPrefix === '' ? '/pwa-icon' : "/{$pathPrefix}/pwa-icon";
        $version = $icon ? $this->pwaIconService->version($icon) : null;

        $icons = $icon ? [
            ['src' => url("{$iconBase}/192.png?v={$version}"), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => url("{$iconBase}/512.png?v={$version}"), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ] : [];

        return response()
            ->json([
                'name' => $name,
                'short_name' => $name,
                'description' => $name,
                'id' => $startUrl,
                'start_url' => $startUrl,
                'scope' => $startUrl,
                'display' => 'standalone',
                'orientation' => 'portrait-primary',
                'background_color' => '#ffffff',
                'theme_color' => '#991b1b',
                'icons' => $icons,
            ])
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'no-cache, must-revalidate');
    }

    public function icon(int $size): Response
    {
        $store = $this->context->store();
        $website = $this->context->website();
        $icon = $store && $website ? $this->pwaIconService->source($store, $website) : null;

        abort_unless($icon, 404);

        try {
            $contents = $this->pwaIconService->render($icon, $size);
        } catch (RuntimeException) {
            abort(404);
        }

        return response($contents, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
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
}
