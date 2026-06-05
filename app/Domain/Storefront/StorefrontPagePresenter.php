<?php

namespace App\Domain\Storefront;

use App\Models\Media;
use App\Models\Product;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageSection;
use Illuminate\Support\Collection;

class StorefrontPagePresenter
{
    /**
     * @return array{id: int, title: string, slug: string, sections: list<array<string, mixed>>}
     */
    public function present(StorefrontPage $page, bool $activeOnly = true): array
    {
        $sections = $page->sections;

        if ($activeOnly) {
            $sections = $sections->where('is_active', true);
        }

        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'sections' => $sections
                ->sortBy('sort_order')
                ->values()
                ->map(fn (StorefrontPageSection $section) => $this->presentSection($section))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentSection(StorefrontPageSection $section): array
    {
        $settings = $this->resolveMedia($section->settings ?? []);

        if ($section->type === StorefrontPageSection::TYPE_FEATURED_PRODUCTS) {
            $settings = $this->resolveProducts($settings);
        }

        return [
            'id' => $section->id,
            'type' => $section->type,
            'sort_order' => $section->sort_order,
            'is_active' => $section->is_active,
            'settings' => $settings,
        ];
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

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function resolveProducts(array $settings): array
    {
        $ids = $settings['product_ids'] ?? [];

        if (empty($ids) || ! is_array($ids)) {
            return $settings;
        }

        $products = Product::query()
            ->active()
            ->with('media')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $settings['products'] = array_values(
            array_filter(array_map(fn (int $id) => $this->productPayload($products->get($id)), $ids)),
        );

        return $settings;
    }

    /**
     * @return array{id: int, name: string, sku: string, thumbnail: string|null}|null
     */
    private function productPayload(?Product $product): ?array
    {
        if (! $product) {
            return null;
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'thumbnail' => $product->primaryMedia('gallery')?->url,
        ];
    }
}
