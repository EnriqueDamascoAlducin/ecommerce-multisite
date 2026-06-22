<?php

namespace App\Domain\Storefront;

use App\Domain\Catalog\BundleService;
use App\Domain\Catalog\ConfigurableProductService;
use App\Domain\Catalog\ProductPricingService;
use App\Domain\Inventory\StockAvailabilityChecker;
use App\Domain\Store\StoreContext;
use App\Models\Category;
use App\Models\HeaderMenuItem;
use App\Models\Media;
use App\Models\Product;
use App\Models\Store;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageSection;
use Illuminate\Support\Collection;

class StorefrontPagePresenter
{
    public function __construct(
        private readonly ProductPricingService $pricing,
        private readonly ConfigurableProductService $configurable,
        private readonly BundleService $bundles,
        private readonly StockAvailabilityChecker $availability,
    ) {}

    /**
     * @return array{id: int, title: string, slug: string, sections: list<array<string, mixed>>}
     */
    public function present(StorefrontPage $page, ?Store $store = null): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'sections' => $page->sections
                ->whereIn('type', StorefrontPageSection::TYPES)
                ->sortBy(fn (StorefrontPageSection $section) => $this->sectionOrder($section))
                ->values()
                ->map(fn (StorefrontPageSection $section) => $this->presentSection($section, $page, $store))
                ->all(),
        ];
    }

    private function sectionOrder(StorefrontPageSection $section): int
    {
        $settings = $section->settings ?? [];
        $displayOrder = $settings['display_order'] ?? null;

        if (is_numeric($displayOrder)) {
            return (int) $displayOrder;
        }

        $templateOrder = array_search($section->type, StorefrontPageSection::TYPES, true);

        return $templateOrder === false ? PHP_INT_MAX : $templateOrder;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentSection(StorefrontPageSection $section, ?StorefrontPage $page = null, ?Store $store = null): array
    {
        $settings = $this->resolveMedia($section->settings ?? []);

        if ($section->type === StorefrontPageSection::TYPE_RECOMMENDED_PRODUCTS && $page) {
            $settings['products'] = $this->recommendedProducts($settings, $page, $store ?? $page->store);
        }

        if ($section->type === StorefrontPageSection::TYPE_BRAND_STRIP && $store) {
            $settings['brands'] = $this->brandLinks($settings, $store);
        }

        return [
            'id' => $section->id,
            'type' => $section->type,
            'settings' => $settings,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>|string>
     */
    private function brandLinks(array $settings, Store $store): array
    {
        return collect($settings['brands'] ?? [])
            ->map(function (mixed $brand) use ($store): mixed {
                if (! is_array($brand)) {
                    return $brand;
                }

                return [
                    ...$brand,
                    'url' => $this->brandUrl($brand, $store),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $brand
     */
    private function brandUrl(array $brand, Store $store): ?string
    {
        return match ($brand['link_type'] ?? 'none') {
            HeaderMenuItem::TYPE_CATEGORY => $this->categoryUrl($brand, $store),
            HeaderMenuItem::TYPE_PRODUCT => $this->productUrl($brand, $store),
            HeaderMenuItem::TYPE_PAGE => $this->pageUrl($brand, $store),
            HeaderMenuItem::TYPE_CUSTOM => trim((string) ($brand['url'] ?? '')) ?: null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $brand
     */
    private function categoryUrl(array $brand, Store $store): ?string
    {
        $source = Category::find($brand['category_id'] ?? null);
        $category = $source
            ? Category::query()
                ->where('website_id', $store->website_id)
                ->where('slug', $source->slug)
                ->active()
                ->first()
            : null;

        return $category ? $this->storefrontPath($store, "/c/{$category->slug}") : null;
    }

    /**
     * @param  array<string, mixed>  $brand
     */
    private function productUrl(array $brand, Store $store): ?string
    {
        $product = Product::query()
            ->active()
            ->whereKey($brand['product_id'] ?? null)
            ->whereHas('storeLinks', fn ($query) => $query
                ->where('store_id', $store->id)
                ->where('is_active', true))
            ->first();

        return $product ? $this->storefrontPath($store, "/p/{$product->slug}") : null;
    }

    /**
     * @param  array<string, mixed>  $brand
     */
    private function pageUrl(array $brand, Store $store): ?string
    {
        $page = StorefrontPage::query()
            ->whereKey($brand['page_id'] ?? null)
            ->where('is_published', true)
            ->whereHas('stores', fn ($query) => $query->where('stores.id', $store->id))
            ->first();

        return $page ? $this->storefrontPath($store, $page->slug === StorefrontPage::HOME ? '/' : "/{$page->slug}") : null;
    }

    private function storefrontPath(Store $store, string $path): string
    {
        $prefix = app(StoreContext::class)->pathPrefix();
        $path = '/'.ltrim($path, '/');

        return $prefix !== '' && $prefix === $store->code ? '/'.$prefix.($path === '/' ? '' : $path) : $path;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function recommendedProducts(array $settings, StorefrontPage $page, Store $store): array
    {
        $productIds = collect($settings['product_ids'] ?? [])
            ->filter(fn (mixed $id) => is_numeric($id))
            ->map(fn (mixed $id) => (int) $id)
            ->unique()
            ->take(12)
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $websiteId = $store->website_id;

        $products = Product::query()
            ->with(['prices', 'media', 'inventoryStocks', 'labels', 'bundleItems.product.prices', 'bundleItems.product.inventoryStocks'])
            ->whereIn('id', $productIds)
            ->where('status', Product::STATUS_ACTIVE)
            ->whereIn('visibility', ['both', 'catalog'])
            ->whereHas('storeLinks', fn ($query) => $query
                ->where('store_id', $store->id)
                ->where('is_active', true))
            ->get()
            ->keyBy('id');

        return $productIds
            ->map(fn (int $productId) => $products->get($productId))
            ->filter()
            ->map(fn (Product $product) => $this->productCard($product, $store->id, $websiteId))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function productCard(Product $product, int $storeId, ?int $websiteId): array
    {
        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => match (true) {
                $product->isConfigurable() => $this->configurable->priceForConfigurable($product, $storeId)
                    ?? ['price' => null, 'special_price' => null, 'effective_price' => null, 'is_special' => false],
                $product->isBundle() => $this->bundles->priceFor($product, $storeId),
                default => $this->pricing->priceFor($product, $storeId),
            },
            'thumbnail' => $product->primaryMedia('gallery')?->url,
            'in_stock' => match (true) {
                $product->isBundle() => $this->bundles->canFulfill($product, 1),
                default => $this->availability->canFulfill($product, 1),
            },
            'requires_options' => $product->isConfigurable(),
            'labels' => $this->labelsFor($product, $websiteId),
        ];
    }

    /**
     * @return list<array{text: string, text_color: string, background_color: string}>
     */
    private function labelsFor(Product $product, ?int $websiteId): array
    {
        $labels = $product->labels->where('is_active', true);

        if ($websiteId) {
            $labels = $labels->where('website_id', $websiteId);
        }

        return $labels
            ->sortBy('sort_order')
            ->map(fn ($label) => [
                'text' => $label->text,
                'text_color' => $label->text_color,
                'background_color' => $label->background_color,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function resolveMedia(array $settings): array
    {
        $mediaIds = $this->collectMediaIds($settings)->unique()->values();
        $media = $mediaIds->isEmpty()
            ? collect()
            : Media::query()->whereIn('id', $mediaIds)->get()->keyBy('id');

        return $this->attachMedia($settings, $media);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return Collection<int, int>
     */
    private function collectMediaIds(array $value): Collection
    {
        $ids = collect();

        foreach ($value as $key => $item) {
            if ($key === 'media_id' && is_numeric($item)) {
                $ids->push((int) $item);
            }

            if (is_array($item)) {
                $ids = $ids->merge($this->collectMediaIds($item));
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  Collection<int, Media>  $media
     * @return array<string, mixed>
     */
    private function attachMedia(array $value, Collection $media): array
    {
        foreach ($value as $key => $item) {
            if ($key === 'media_id' && is_numeric($item)) {
                $value['media'] = $this->mediaPayload($media->get((int) $item));
            }

            if (is_array($item)) {
                $value[$key] = $this->attachMedia($item, $media);
            }
        }

        return $value;
    }

    /**
     * @return array{id: int, url: string, alt: string|null}|null
     */
    private function mediaPayload(?Media $media): ?array
    {
        if (! $media) {
            return null;
        }

        return [
            'id' => $media->id,
            'url' => $media->url,
            'alt' => $media->alt,
        ];
    }
}
