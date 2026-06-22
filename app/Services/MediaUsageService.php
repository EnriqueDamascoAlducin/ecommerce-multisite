<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\Store;
use App\Models\StorefrontPageSection;
use App\Models\Website;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MediaUsageService
{
    /** @var array<string, string> */
    private const DIRECT_CONTEXTS = [
        Product::class => 'products',
        Category::class => 'categories',
        Store::class => 'stores',
        Website::class => 'websites',
    ];

    /** @var array<string, string> */
    private const DIRECT_LABELS = [
        'products' => 'Producto',
        'categories' => 'Categoria',
        'stores' => 'Tienda',
        'websites' => 'Website',
    ];

    /** @return list<int> */
    public function usedMediaIds(string $context = 'all'): array
    {
        return $this->usageIndex($context)
            ->keys()
            ->map(fn (int|string $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Media>  $media
     * @return array<int, list<array{context: string, label: string, title: string, description: string|null}>>
     */
    public function usagesFor(Collection $media, string $context = 'all'): array
    {
        $ids = $media->pluck('id')->map(fn (int|string $id): int => (int) $id)->all();

        if ($ids === []) {
            return [];
        }

        return $this->usageIndex($context, $ids)->all();
    }

    /**
     * @param  list<int>|null  $onlyMediaIds
     * @return Collection<int, list<array{context: string, label: string, title: string, description: string|null}>>
     */
    private function usageIndex(string $context = 'all', ?array $onlyMediaIds = null): Collection
    {
        $index = collect();

        if ($this->includesDirectContext($context)) {
            $this->appendDirectUsages($index, $context, $onlyMediaIds);
        }

        if ($this->matchesContext($context, ['all', 'pages', 'sections'])) {
            $this->appendSectionUsages($index, $onlyMediaIds);
        }

        if ($this->matchesContext($context, ['all', 'pages', 'seo'])) {
            $this->appendSeoUsages($index, $onlyMediaIds);
        }

        if ($this->matchesContext($context, ['all', 'header'])) {
            $this->appendHeaderUsages($index, $onlyMediaIds);
        }

        return $index->map(fn (array $usages): array => array_values($usages));
    }

    /**
     * @param  Collection<int, list<array{context: string, label: string, title: string, description: string|null}>>  $index
     * @param  list<int>|null  $onlyMediaIds
     */
    private function appendDirectUsages(Collection $index, string $context, ?array $onlyMediaIds): void
    {
        $rows = DB::table('mediables')
            ->select(['media_id', 'mediable_type', 'mediable_id', 'collection'])
            ->when($onlyMediaIds !== null, fn ($query) => $query->whereIn('media_id', $onlyMediaIds))
            ->whereIn('mediable_type', array_keys(self::DIRECT_CONTEXTS))
            ->get();

        foreach ($rows->groupBy('mediable_type') as $type => $typeRows) {
            $directContext = self::DIRECT_CONTEXTS[$type] ?? null;

            if ($directContext === null || ! $this->matchesContext($context, ['all', $directContext])) {
                continue;
            }

            $models = $type::query()
                ->whereIn('id', $typeRows->pluck('mediable_id')->unique()->all())
                ->get(['id', 'name', ...($type === Product::class ? ['sku'] : [])])
                ->keyBy('id');

            foreach ($typeRows as $row) {
                $model = $models->get($row->mediable_id);
                $title = $model?->name ?? "#{$row->mediable_id}";

                if ($type === Product::class && $model?->sku) {
                    $title = "{$model->sku} - {$model->name}";
                }

                $this->pushUsage($index, (int) $row->media_id, [
                    'context' => $directContext,
                    'label' => self::DIRECT_LABELS[$directContext],
                    'title' => $title,
                    'description' => $row->collection ? "Coleccion {$row->collection}" : null,
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, list<array{context: string, label: string, title: string, description: string|null}>>  $index
     * @param  list<int>|null  $onlyMediaIds
     */
    private function appendSectionUsages(Collection $index, ?array $onlyMediaIds): void
    {
        StorefrontPageSection::query()
            ->with('page:id,title,slug')
            ->get(['id', 'storefront_page_id', 'type', 'settings'])
            ->each(function (StorefrontPageSection $section) use ($index, $onlyMediaIds): void {
                foreach ($this->extractMediaIds($section->settings) as $mediaId) {
                    if ($onlyMediaIds !== null && ! in_array($mediaId, $onlyMediaIds, true)) {
                        continue;
                    }

                    $this->pushUsage($index, $mediaId, [
                        'context' => 'sections',
                        'label' => 'Seccion',
                        'title' => $section->page?->title ?? 'Pagina CMS',
                        'description' => $this->sectionLabel($section->type),
                    ]);
                }
            });
    }

    /**
     * @param  Collection<int, list<array{context: string, label: string, title: string, description: string|null}>>  $index
     * @param  list<int>|null  $onlyMediaIds
     */
    private function appendSeoUsages(Collection $index, ?array $onlyMediaIds): void
    {
        DB::table('storefront_page_store')
            ->join('storefront_pages', 'storefront_pages.id', '=', 'storefront_page_store.storefront_page_id')
            ->join('stores', 'stores.id', '=', 'storefront_page_store.store_id')
            ->whereNotNull('storefront_page_store.og_media_id')
            ->when($onlyMediaIds !== null, fn ($query) => $query->whereIn('storefront_page_store.og_media_id', $onlyMediaIds))
            ->get([
                'storefront_page_store.og_media_id',
                'storefront_pages.title as page_title',
                'stores.name as store_name',
            ])
            ->each(function (object $row) use ($index): void {
                $this->pushUsage($index, (int) $row->og_media_id, [
                    'context' => 'seo',
                    'label' => 'SEO',
                    'title' => (string) $row->page_title,
                    'description' => "Open Graph en {$row->store_name}",
                ]);
            });
    }

    /**
     * @param  Collection<int, list<array{context: string, label: string, title: string, description: string|null}>>  $index
     * @param  list<int>|null  $onlyMediaIds
     */
    private function appendHeaderUsages(Collection $index, ?array $onlyMediaIds): void
    {
        DB::table('website_header_settings')
            ->join('websites', 'websites.id', '=', 'website_header_settings.website_id')
            ->get(['websites.name', 'website_header_settings.cintillo_blocks'])
            ->each(function (object $row) use ($index, $onlyMediaIds): void {
                foreach ($this->extractMediaIds($this->decodeJson($row->cintillo_blocks)) as $mediaId) {
                    if ($onlyMediaIds !== null && ! in_array($mediaId, $onlyMediaIds, true)) {
                        continue;
                    }

                    $this->pushUsage($index, $mediaId, [
                        'context' => 'header',
                        'label' => 'Cintillo',
                        'title' => (string) $row->name,
                        'description' => 'Website base',
                    ]);
                }
            });

        DB::table('store_configurations')
            ->leftJoin('stores', 'stores.id', '=', 'store_configurations.scope_id')
            ->where('store_configurations.scope', 'store')
            ->where('store_configurations.key', 'header.cintillo')
            ->get(['stores.name', 'store_configurations.value'])
            ->each(function (object $row) use ($index, $onlyMediaIds): void {
                foreach ($this->extractMediaIds($this->decodeJson($row->value)) as $mediaId) {
                    if ($onlyMediaIds !== null && ! in_array($mediaId, $onlyMediaIds, true)) {
                        continue;
                    }

                    $this->pushUsage($index, $mediaId, [
                        'context' => 'header',
                        'label' => 'Cintillo',
                        'title' => (string) ($row->name ?? 'Tienda'),
                        'description' => 'Override de tienda',
                    ]);
                }
            });
    }

    /** @return list<int> */
    private function extractMediaIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $key => $item) {
            if ($key === 'media_id' && is_numeric($item)) {
                $ids[] = (int) $item;

                continue;
            }

            if (is_array($item)) {
                $ids = [...$ids, ...$this->extractMediaIds($item)];
            }
        }

        return array_values(array_unique(array_filter($ids, fn (int $id): bool => $id > 0)));
    }

    /** @return array<string, mixed>|list<mixed> */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  Collection<int, list<array{context: string, label: string, title: string, description: string|null}>>  $index
     * @param  array{context: string, label: string, title: string, description: string|null}  $usage
     */
    private function pushUsage(Collection $index, int $mediaId, array $usage): void
    {
        $usages = $index->get($mediaId, []);
        $key = implode('|', $usage);

        $usages[$key] = $usage;
        $index->put($mediaId, $usages);
    }

    /** @param  list<string>  $contexts */
    private function matchesContext(string $context, array $contexts): bool
    {
        return in_array($context, $contexts, true);
    }

    private function includesDirectContext(string $context): bool
    {
        return $context === 'all' || in_array($context, array_values(self::DIRECT_CONTEXTS), true);
    }

    private function sectionLabel(string $type): string
    {
        return match ($type) {
            'hero' => 'Hero',
            'specialty_grid' => 'Especialidades',
            'feature_cards' => 'Tarjetas',
            'brand_strip' => 'Marcas',
            'inquiry_form' => 'Contacto',
            'recommended_products' => 'Productos recomendados',
            'image_banner' => 'Banner',
            'page_header' => 'Encabezado',
            'rich_text' => 'Texto enriquecido',
            'contact_info' => 'Informacion de contacto',
            default => $type,
        };
    }
}
