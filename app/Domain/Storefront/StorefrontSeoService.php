<?php

namespace App\Domain\Storefront;

use App\Domain\Store\ScopedConfigService;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\Store;
use App\Models\StorefrontPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StorefrontSeoService
{
    public const INDEXING_KEY = 'seo.robots.indexing_enabled';

    public const RULES_KEY = 'seo.robots.additional_rules';

    private const CACHE_SECONDS = 3600;

    public function __construct(private readonly ScopedConfigService $config) {}

    /**
     * @return array{
     *     title: string,
     *     description: string|null,
     *     keywords: string|null,
     *     canonical_url: string,
     *     robots: string,
     *     og_title: string,
     *     og_description: string|null,
     *     og_image: string|null
     * }
     */
    public function pageMeta(StorefrontPage $page, Store $store): array
    {
        $assignedStore = $page->relationLoaded('stores')
            ? $page->stores->firstWhere('id', $store->id)
            : null;
        $assignedStore ??= $page->stores()->whereKey($store->id)->first();
        $pivot = $assignedStore?->pivot;

        $title = trim((string) ($pivot?->meta_title ?: $page->title));
        $description = $this->nullableString($pivot?->meta_description);
        $keywords = $this->nullableString($pivot?->meta_keywords);
        $canonicalUrl = $this->nullableString($pivot?->canonical_url)
            ?? $this->pageUrl($page, $store);
        $robotsIndex = $pivot?->robots_index === null || (bool) $pivot->robots_index;
        $robotsFollow = $pivot?->robots_follow === null || (bool) $pivot->robots_follow;
        $ogMediaId = $pivot?->og_media_id ? (int) $pivot->og_media_id : null;
        $ogImage = $ogMediaId ? Media::find($ogMediaId)?->url : null;

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical_url' => $canonicalUrl,
            'robots' => ($robotsIndex ? 'index' : 'noindex').', '.($robotsFollow ? 'follow' : 'nofollow'),
            'og_title' => trim((string) ($pivot?->og_title ?: $title)),
            'og_description' => $this->nullableString($pivot?->og_description) ?? $description,
            'og_image' => $ogImage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function editablePageMeta(StorefrontPage $page, Store $store): array
    {
        $assignedStore = $page->relationLoaded('stores')
            ? $page->stores->firstWhere('id', $store->id)
            : null;
        $assignedStore ??= $page->stores()->whereKey($store->id)->first();
        $pivot = $assignedStore?->pivot;
        $media = $pivot?->og_media_id ? Media::find((int) $pivot->og_media_id) : null;

        return [
            'meta_title' => $pivot?->meta_title,
            'meta_description' => $pivot?->meta_description,
            'meta_keywords' => $pivot?->meta_keywords,
            'robots_index' => $pivot?->robots_index === null || (bool) $pivot->robots_index,
            'robots_follow' => $pivot?->robots_follow === null || (bool) $pivot->robots_follow,
            'canonical_url' => $pivot?->canonical_url,
            'og_title' => $pivot?->og_title,
            'og_description' => $pivot?->og_description,
            'og_media_id' => $pivot?->og_media_id ? (int) $pivot->og_media_id : null,
            'og_media' => $media ? [
                'id' => $media->id,
                'url' => $media->url,
                'alt' => $media->alt,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $seo
     * @return array<string, mixed>
     */
    public function normalizePageMeta(array $seo): array
    {
        return [
            'meta_title' => $this->nullableString($seo['meta_title'] ?? null),
            'meta_description' => $this->nullableString($seo['meta_description'] ?? null),
            'meta_keywords' => $this->nullableString($seo['meta_keywords'] ?? null),
            'robots_index' => (bool) ($seo['robots_index'] ?? true),
            'robots_follow' => (bool) ($seo['robots_follow'] ?? true),
            'canonical_url' => $this->nullableString($seo['canonical_url'] ?? null),
            'og_title' => $this->nullableString($seo['og_title'] ?? null),
            'og_description' => $this->nullableString($seo['og_description'] ?? null),
            'og_media_id' => isset($seo['og_media_id']) ? (int) $seo['og_media_id'] : null,
        ];
    }

    public function baseUrl(Store $store): string
    {
        $store->loadMissing('domains', 'website.stores.domains');

        $domain = $store->domains->firstWhere('is_primary', true) ?? $store->domains->first();

        if ($domain) {
            return 'https://'.$domain->host;
        }

        $entryStore = $store->website->stores->firstWhere('is_default', true)
            ?? $store->website->stores->sortBy('sort_order')->first();
        $entryDomain = $entryStore?->domains->firstWhere('is_primary', true)
            ?? $entryStore?->domains->first();
        $root = $entryDomain ? 'https://'.$entryDomain->host : rtrim((string) config('app.url'), '/');

        return $store->is_default ? $root : $root.'/'.$store->code;
    }

    public function pageUrl(StorefrontPage $page, Store $store): string
    {
        $base = $this->baseUrl($store);

        return $page->slug === StorefrontPage::HOME ? $base.'/' : $base.'/'.$page->slug;
    }

    public function sitemapUrl(Store $store): string
    {
        return $this->baseUrl($store).'/sitemap.xml';
    }

    public function robotsUrl(Store $store): string
    {
        return $this->baseUrl($this->entryStoreFor($store)).'/robots.txt';
    }

    public function sitemapXml(Store $store): string
    {
        return Cache::remember(
            $this->sitemapCacheKey($store),
            self::CACHE_SECONDS,
            fn (): string => $this->buildSitemap($store),
        );
    }

    public function robotsText(Store $store): string
    {
        $entryStore = $this->entryStoreFor($store);

        return Cache::remember(
            $this->robotsCacheKey($entryStore),
            self::CACHE_SECONDS,
            fn (): string => $this->buildRobots($entryStore),
        );
    }

    /**
     * @return array{pages: int, categories: int, products: int}
     */
    public function counts(Store $store): array
    {
        return [
            'pages' => $this->pageQuery($store)->count(),
            'categories' => $this->categoryQuery($store)->count(),
            'products' => $this->productQuery($store)->count(),
        ];
    }

    public function indexingEnabled(Store $store): bool
    {
        $store->loadMissing('website');

        return $this->config->get(self::INDEXING_KEY, '1', $store->website, $store) !== '0';
    }

    public function additionalRules(Store $store): string
    {
        $store->loadMissing('website');

        return (string) $this->config->get(self::RULES_KEY, '', $store->website, $store);
    }

    public function saveRobotsSettings(Store $store, bool $indexingEnabled, ?string $additionalRules): void
    {
        $this->config->set(
            ScopedConfigService::SCOPE_STORE,
            $store->id,
            self::INDEXING_KEY,
            $indexingEnabled ? '1' : '0',
        );
        $this->config->set(
            ScopedConfigService::SCOPE_STORE,
            $store->id,
            self::RULES_KEY,
            $this->nullableString($additionalRules),
        );

        $this->forget($store);
    }

    public function regenerate(Store $store): void
    {
        $this->forget($store);
        $this->sitemapXml($store);
        $this->robotsText($store);
    }

    public function forget(Store $store): void
    {
        Cache::forget($this->sitemapCacheKey($store));
        Cache::forget($this->robotsCacheKey($this->entryStoreFor($store)));
    }

    public function entryStoreFor(Store $store): Store
    {
        $store->loadMissing('domains', 'website.stores.domains');

        if ($store->domains->isNotEmpty()) {
            return $store;
        }

        return $store->website->stores->firstWhere('is_default', true)
            ?? $store->website->stores->sortBy('sort_order')->first()
            ?? $store;
    }

    private function buildSitemap(Store $store): string
    {
        $urls = [];

        foreach ($this->pageQuery($store)->get() as $page) {
            $urls[] = [
                'loc' => $this->pageUrl($page, $store),
                'lastmod' => $page->updated_at?->toAtomString(),
            ];
        }

        foreach ($this->categoryQuery($store)->get() as $category) {
            $urls[] = [
                'loc' => $this->baseUrl($store).'/c/'.$category->slug,
                'lastmod' => $category->updated_at?->toAtomString(),
            ];
        }

        foreach ($this->productQuery($store)->get() as $product) {
            $urls[] = [
                'loc' => $this->baseUrl($store).'/p/'.$product->slug,
                'lastmod' => $product->updated_at?->toAtomString(),
            ];
        }

        $rows = collect($urls)->map(function (array $url): string {
            $lastmod = $url['lastmod']
                ? '<lastmod>'.$this->escapeXml($url['lastmod']).'</lastmod>'
                : '';

            return '  <url><loc>'.$this->escapeXml($url['loc']).'</loc>'.$lastmod.'</url>';
        })->implode("\n");

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$rows."\n"
            .'</urlset>';
    }

    private function buildRobots(Store $entryStore): string
    {
        $entryStore->loadMissing('website.stores.domains');
        $stores = $entryStore->website->stores
            ->where('is_active', true)
            ->sortBy(fn (Store $store) => $store->id === $entryStore->id ? -1 : $store->sort_order)
            ->values();

        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /api',
            'Disallow: /cuenta',
            'Disallow: /carrito',
            'Disallow: /checkout',
            'Disallow: /buscar',
        ];

        $rootEnabled = $this->indexingEnabled($entryStore);

        if (! $rootEnabled) {
            $lines[] = 'Disallow: /';
        }

        foreach ($stores as $store) {
            $prefix = $store->id === $entryStore->id ? '' : '/'.$store->code;

            if ($store->id !== $entryStore->id) {
                $lines[] = ($this->indexingEnabled($store) ? 'Allow: ' : 'Disallow: ').$prefix.'/';
            }

            foreach ($this->validatedRules($store) as [$directive, $path]) {
                $lines[] = $directive.': '.$this->prefixedRulePath($prefix, $path);
            }
        }

        $lines[] = '';

        foreach ($stores->filter(fn (Store $store) => $this->indexingEnabled($store)) as $store) {
            $lines[] = 'Sitemap: '.$this->sitemapUrl($store);
        }

        return implode("\n", array_values(array_unique($lines)))."\n";
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function validatedRules(Store $store): array
    {
        return Str::of($this->additionalRules($store))
            ->explode("\n")
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->map(function (string $line): ?array {
                if (! preg_match('/^(Allow|Disallow):\\s*(\\/.*)$/i', $line, $matches)) {
                    return null;
                }

                return [ucfirst(strtolower($matches[1])), $matches[2]];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function prefixedRulePath(string $prefix, string $path): string
    {
        if ($prefix === '') {
            return $path;
        }

        return $path === '/' ? $prefix.'/' : $prefix.$path;
    }

    /**
     * @return Builder<StorefrontPage>
     */
    private function pageQuery(Store $store): Builder
    {
        return StorefrontPage::query()
            ->where('is_published', true)
            ->whereHas('stores', fn (Builder $query) => $query
                ->whereKey($store->id)
                ->where('storefront_page_store.robots_index', true))
            ->orderByRaw("CASE WHEN slug = 'home' THEN 0 ELSE 1 END")
            ->orderBy('id');
    }

    /**
     * @return Builder<Category>
     */
    private function categoryQuery(Store $store): Builder
    {
        return Category::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->orderBy('id');
    }

    /**
     * @return Builder<Product>
     */
    private function productQuery(Store $store): Builder
    {
        return Product::query()
            ->where('status', Product::STATUS_ACTIVE)
            ->where('visibility', '!=', 'hidden')
            ->whereHas('storeLinks', fn (Builder $query) => $query
                ->where('store_id', $store->id)
                ->where('is_active', true))
            ->orderBy('id');
    }

    private function sitemapCacheKey(Store $store): string
    {
        return 'seo:sitemap:store:'.$store->id;
    }

    private function robotsCacheKey(Store $entryStore): string
    {
        return 'seo:robots:'.md5($this->baseUrl($entryStore));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

