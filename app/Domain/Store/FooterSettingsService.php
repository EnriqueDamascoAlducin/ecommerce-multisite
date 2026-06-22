<?php

namespace App\Domain\Store;

use App\Models\Category;
use App\Models\HeaderMenuItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreConfiguration;
use App\Models\StorefrontPage;
use App\Models\Website;
use App\Models\WebsiteHeaderSettings;
use JsonException;

class FooterSettingsService
{
    public const CONFIG_KEY = 'header.footer';

    public const LINK_TYPES = [
        HeaderMenuItem::TYPE_PAGE,
        HeaderMenuItem::TYPE_CATEGORY,
        HeaderMenuItem::TYPE_PRODUCT,
        HeaderMenuItem::TYPE_ALL_CATEGORIES,
        HeaderMenuItem::TYPE_CUSTOM,
    ];

    /** @param array<string, mixed>|null $footer */
    public function normalize(?array $footer, Website $website): array
    {
        $footer = [
            'enabled' => true,
            'description' => '',
            'copyright' => "\u{00A9} {year} {$website->name}. Todos los derechos reservados.",
            'background_color' => null,
            'text_color' => null,
            'columns' => [],
            'contact' => [],
            'social' => [],
            ...($footer ?? []),
        ];

        return [
            'enabled' => (bool) $footer['enabled'],
            'description' => trim((string) $footer['description']),
            'copyright' => trim((string) $footer['copyright']),
            'background_color' => $this->nullableString($footer['background_color']),
            'text_color' => $this->nullableString($footer['text_color']),
            'columns' => $this->normalizeColumns($footer['columns']),
            'contact' => $this->normalizeContact($footer['contact']),
            'social' => $this->normalizeSocial($footer['social']),
        ];
    }

    public function override(Store $store): ?array
    {
        $value = StoreConfiguration::query()
            ->where('scope', ScopedConfigService::SCOPE_STORE)
            ->where('scope_id', $store->id)
            ->where('key', self::CONFIG_KEY)
            ->value('value');

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $this->normalize($decoded, $store->website) : null;
    }

    /** @param array<string, mixed> $footer */
    public function saveOverride(Store $store, array $footer): void
    {
        StoreConfiguration::updateOrCreate(
            ['scope' => ScopedConfigService::SCOPE_STORE, 'scope_id' => $store->id, 'key' => self::CONFIG_KEY],
            ['value' => json_encode($this->normalize($footer, $store->website), JSON_THROW_ON_ERROR)],
        );
    }

    public function deleteOverride(Store $store): void
    {
        StoreConfiguration::query()
            ->where('scope', ScopedConfigService::SCOPE_STORE)
            ->where('scope_id', $store->id)
            ->where('key', self::CONFIG_KEY)
            ->delete();
    }

    /** @param array<string, mixed>|null $websiteFooter */
    public function resolvedFor(Website $website, Store $store, ?array $websiteFooter): array
    {
        $footer = $this->override($store) ?? $this->normalize($websiteFooter, $website);
        $footer['columns'] = collect($footer['columns'])
            ->map(function (array $column) use ($store) {
                $column['links'] = collect($column['links'])
                    ->map(fn (array $link) => $this->resolveLink($link, $store))
                    ->filter()->values()->all();

                return $column['title'] === '' && $column['links'] === [] ? null : $column;
            })->filter()->values()->all();

        return $footer;
    }

    /** @return array{categories: array, products: array, pages: array} */
    public function optionsFor(Store $store): array
    {
        return [
            'categories' => Category::query()->where('store_id', $store->id)->active()->orderBy('name')
                ->get(['id', 'name'])->map(fn (Category $item) => ['id' => $item->id, 'label' => $item->name])->all(),
            'products' => Product::query()->active()
                ->whereHas('storeLinks', fn ($query) => $query->where('store_id', $store->id)->where('is_active', true))
                ->orderBy('name')->get(['id', 'name', 'sku'])
                ->map(fn (Product $item) => ['id' => $item->id, 'label' => $item->sku ? "{$item->name} ({$item->sku})" : $item->name])->all(),
            'pages' => StorefrontPage::query()->where('is_published', true)
                ->whereHas('stores', fn ($query) => $query->where('stores.id', $store->id))
                ->orderBy('title')->get(['id', 'title'])
                ->map(fn (StorefrontPage $item) => ['id' => $item->id, 'label' => $item->title])->all(),
        ];
    }

    private function resolveLink(array $link, Store $store): ?array
    {
        $label = trim((string) ($link['label'] ?? ''));
        $url = match ($link['type'] ?? HeaderMenuItem::TYPE_CUSTOM) {
            HeaderMenuItem::TYPE_ALL_CATEGORIES => $this->storefrontPath($store, '/buscar'),
            HeaderMenuItem::TYPE_CATEGORY => $this->categoryUrl($link, $store),
            HeaderMenuItem::TYPE_PRODUCT => $this->productUrl($link, $store),
            HeaderMenuItem::TYPE_PAGE => $this->pageUrl($link, $store),
            HeaderMenuItem::TYPE_CUSTOM => trim((string) ($link['url'] ?? '')) ?: null,
            default => null,
        };

        return $label !== '' && $url ? ['label' => $label, 'url' => $url] : null;
    }

    private function categoryUrl(array $link, Store $store): ?string
    {
        $source = Category::find($link['category_id'] ?? null);
        $category = $source ? Category::query()->where('store_id', $store->id)
            ->where('slug', $source->slug)->active()->first() : null;

        return $category ? $this->storefrontPath($store, "/c/{$category->slug}") : null;
    }

    private function productUrl(array $link, Store $store): ?string
    {
        $product = Product::query()->active()->whereKey($link['product_id'] ?? null)
            ->whereHas('storeLinks', fn ($query) => $query->where('store_id', $store->id)->where('is_active', true))->first();

        return $product ? $this->storefrontPath($store, "/p/{$product->slug}") : null;
    }

    private function pageUrl(array $link, Store $store): ?string
    {
        $page = StorefrontPage::query()->whereKey($link['page_id'] ?? null)->where('is_published', true)
            ->whereHas('stores', fn ($query) => $query->where('stores.id', $store->id))->first();

        return $page ? $this->storefrontPath($store, $page->slug === StorefrontPage::HOME ? '/' : "/{$page->slug}") : null;
    }

    private function storefrontPath(Store $store, string $path): string
    {
        $prefix = app(StoreContext::class)->pathPrefix();
        $path = '/'.ltrim($path, '/');

        return $prefix !== '' && $prefix === $store->code ? '/'.$prefix.($path === '/' ? '' : $path) : $path;
    }

    private function normalizeColumns(mixed $columns): array
    {
        return is_array($columns) ? collect($columns)->take(4)->filter(fn ($column) => is_array($column))
            ->map(function (array $column) {
                $links = collect($column['links'] ?? [])->take(8)->filter(fn ($link) => is_array($link))
                    ->map(fn (array $link) => $this->normalizeLink($link))->filter()->values()->all();
                $title = trim((string) ($column['title'] ?? ''));

                return $title === '' && $links === [] ? null : [
                    'title' => $title,
                    'title_color' => $this->nullableString($column['title_color'] ?? null),
                    'link_color' => $this->nullableString($column['link_color'] ?? null),
                    'links' => $links,
                ];
            })->filter()->values()->all() : [];
    }

    private function normalizeLink(array $link): ?array
    {
        $label = trim((string) ($link['label'] ?? ''));
        $type = $link['type'] ?? HeaderMenuItem::TYPE_CUSTOM;

        if ($label === '' || ! in_array($type, self::LINK_TYPES, true)) {
            return null;
        }

        return [
            'label' => $label,
            'type' => $type,
            'url' => $type === HeaderMenuItem::TYPE_CUSTOM ? trim((string) ($link['url'] ?? '')) : null,
            'category_id' => $type === HeaderMenuItem::TYPE_CATEGORY ? $this->nullableInteger($link['category_id'] ?? null) : null,
            'product_id' => $type === HeaderMenuItem::TYPE_PRODUCT ? $this->nullableInteger($link['product_id'] ?? null) : null,
            'page_id' => $type === HeaderMenuItem::TYPE_PAGE ? $this->nullableInteger($link['page_id'] ?? null) : null,
        ];
    }

    private function normalizeContact(mixed $contact): array
    {
        return is_array($contact) ? collect($contact)->take(6)
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['label'] ?? '')) !== '' && trim((string) ($row['value'] ?? '')) !== '')
            ->map(fn ($row) => ['label' => trim((string) $row['label']), 'value' => trim((string) $row['value'])])->values()->all() : [];
    }

    private function normalizeSocial(mixed $social): array
    {
        return is_array($social) ? collect($social)->take(5)
            ->filter(fn ($row) => is_array($row) && in_array($row['platform'] ?? null, WebsiteHeaderSettings::SOCIAL_PLATFORMS, true) && trim((string) ($row['url'] ?? '')) !== '')
            ->map(fn ($row) => ['platform' => $row['platform'], 'url' => trim((string) $row['url'])])->values()->all() : [];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
