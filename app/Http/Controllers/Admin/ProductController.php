<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\BundleService;
use App\Domain\Catalog\ConfigurableProductService;
use App\Domain\Catalog\ProductPricingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductLabel;
use App\Models\Store;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductPricingService $pricing,
        private readonly ConfigurableProductService $configurable,
        private readonly BundleService $bundles,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'type' => $request->string('type')->toString(),
            'visibility' => $request->string('visibility')->toString(),
            'category_id' => $request->string('category_id')->toString(),
            'store_id' => $request->string('store_id')->toString(),
            'label_id' => $request->string('label_id')->toString(),
            'stock' => $request->string('stock')->toString(),
            'price_min' => $request->string('price_min')->toString(),
            'price_max' => $request->string('price_max')->toString(),
            'attrs' => $request->input('attrs', []),
        ];

        $filterableAttributes = Attribute::with('options')
            ->where('is_filterable', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $visibleAttributes = Attribute::query()
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type']);

        $query = Product::query()
            ->whereNull('parent_id')
            ->with([
                'prices',
                'media',
                'labels',
                'categories:id,name',
                'storeLinks.store:id,name,code',
                'inventoryStocks',
                'attributeValues.attribute.options',
            ])
            ->when($filters['search'], fn ($query, $search) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"),
            ))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->when($filters['visibility'], fn ($query, $visibility) => $query->where('visibility', $visibility))
            ->when($filters['category_id'], fn ($query, $categoryId) => $query->whereHas('categories', fn ($q) => $q->whereKey($categoryId)))
            ->when($filters['store_id'], fn ($query, $storeId) => $query->whereHas('storeLinks', fn ($q) => $q->where('store_id', $storeId)->where('is_active', true)))
            ->when($filters['label_id'], fn ($query, $labelId) => $query->whereHas('labels', fn ($q) => $q->whereKey($labelId)))
            ->when($filters['stock'] === 'in', fn ($query) => $query->whereHas('inventoryStocks', fn ($q) => $q->whereRaw('(physical_qty - reserved_qty) > 0')))
            ->when($filters['stock'] === 'out', fn ($query) => $query->whereDoesntHave('inventoryStocks', fn ($q) => $q->whereRaw('(physical_qty - reserved_qty) > 0')))
            ->when($filters['price_min'] !== '', fn ($query) => $query->whereHas('prices', fn ($q) => $q->whereNull('store_id')->where('price', '>=', $filters['price_min'])))
            ->when($filters['price_max'] !== '', fn ($query) => $query->whereHas('prices', fn ($q) => $q->whereNull('store_id')->where('price', '<=', $filters['price_max'])));

        $this->applyAttributeFilters($query, is_array($filters['attrs']) ? $filters['attrs'] : [], $filterableAttributes);

        $products = $query
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product) => $this->productGridRow($product));

        return Inertia::render('admin/products/index', [
            'products' => $products,
            'filters' => $filters,
            'filterOptions' => $this->productGridFilterOptions($filterableAttributes),
            'columns' => $this->productGridColumns($visibleAttributes),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/products/create', $this->formData());
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $type = $data['type'] ?? Product::TYPE_SIMPLE;

            $product = Product::create([
                'type' => $type,
                'price_type' => $type === Product::TYPE_BUNDLE
                    ? ($data['price_type'] ?? Product::PRICE_TYPE_DYNAMIC)
                    : null,
                'sku' => $data['sku'],
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['slug'] ?? null, $data['name']),
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
                'visibility' => $data['visibility'],
                'weight' => $data['weight'] ?? null,
            ]);

            $this->persistRelations($product, $data);

            if ($type === Product::TYPE_CONFIGURABLE && ! empty($data['configurable_attributes'])) {
                $attributeIds = array_map('intval', $data['configurable_attributes']);
                $product->configurableAttributes()->sync($attributeIds);
                $this->configurable->generateVariants($product, $attributeIds);
            }

            if ($type === Product::TYPE_BUNDLE) {
                $this->persistBundleItems($product, $data['bundle_items'] ?? []);
            }

            if ($type === Product::TYPE_DOWNLOADABLE) {
                $this->persistDownloadableLinks($product, $data['downloadable_links'] ?? []);
            }

            return $product;
        });

        $this->auditLogger->log('product.created', $product, "Producto {$product->sku} creado");

        return to_route('admin.products.index')->with('success', 'Producto creado.');
    }

    public function edit(Product $product): Response
    {
        $product->load(['prices', 'storeLinks', 'media', 'categories', 'labels', 'attributeValues', 'parent', 'configurableAttributes', 'bundleItems.product', 'downloadableLinks']);

        $base = $product->basePrice();

        $data = [
            'id' => $product->id,
            'type' => $product->type,
            'price_type' => $product->price_type,
            'parent_id' => $product->parent_id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'status' => $product->status,
            'visibility' => $product->visibility,
            'weight' => $product->weight,
            'price' => $base?->price,
            'special_price' => $base?->special_price,
            'special_price_from' => $base?->special_price_from?->toDateString(),
            'special_price_to' => $base?->special_price_to?->toDateString(),
            'stores' => $product->storeLinks->map(function ($link) use ($product) {
                $override = $product->prices->firstWhere('store_id', $link->store_id);

                return [
                    'store_id' => $link->store_id,
                    'is_active' => $link->is_active,
                    'price' => $override?->price,
                    'special_price' => $override?->special_price,
                    'special_price_from' => $override?->special_price_from?->toDateString(),
                    'special_price_to' => $override?->special_price_to?->toDateString(),
                ];
            })->values(),
            'media' => $product->mediaInCollection('gallery')->pluck('id'),
            'categories' => $product->categories->pluck('id'),
            'labels' => $product->labels->pluck('id'),
            'attribute_values' => $product->attributeValues->mapWithKeys(function ($value) {
                $decoded = json_decode((string) $value->value, true);

                return [$value->attribute_id => is_array($decoded) ? $decoded : $value->value];
            }),
        ];

        if ($product->isConfigurable()) {
            $data['configurable_attributes'] = $product->configurableAttributes->pluck('id');

            $data['variants'] = $product->variants()
                ->with(['prices', 'attributeValues.attribute'])
                ->get()
                ->map(fn (Product $variant) => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'status' => $variant->status,
                    'price' => $variant->basePrice()?->price,
                    'options' => $variant->attributeValues->mapWithKeys(fn ($av) => [
                        $av->attribute?->code => $av->value,
                    ]),
                ]);
        } elseif ($product->isBundle()) {
            $data['bundle_items'] = $product->bundleItems->map(fn ($item) => [
                'product_id' => $item->product_id,
                'name' => $item->product?->name,
                'sku' => $item->product?->sku,
                'quantity' => $item->quantity,
            ])->values();
        } elseif ($product->isDownloadable()) {
            $data['downloadable_links'] = $product->downloadableLinks->map(fn ($link) => [
                'id' => $link->id,
                'title' => $link->title,
                'file_path' => $link->file_path,
                'original_name' => $link->original_name,
                'max_downloads' => $link->max_downloads,
            ])->values();
        } elseif ($product->parent) {
            $data['parent_name'] = $product->parent->name;
            $data['parent_id'] = $product->parent->id;
        }

        return Inertia::render('admin/products/edit', [
            ...$this->formData(),
            'product' => $data,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product) {
            $data = $request->validated();

            $product->update([
                'price_type' => $product->isBundle()
                    ? ($data['price_type'] ?? Product::PRICE_TYPE_DYNAMIC)
                    : $product->price_type,
                'sku' => $data['sku'],
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['slug'] ?? null, $data['name'], $product->id),
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
                'visibility' => $data['visibility'],
                'weight' => $data['weight'] ?? null,
            ]);

            $this->persistRelations($product, $data);

            if ($product->isBundle()) {
                $this->persistBundleItems($product, $data['bundle_items'] ?? []);
            }

            if ($product->isDownloadable()) {
                $this->persistDownloadableLinks($product, $data['downloadable_links'] ?? []);
            }

            if ($product->isConfigurable()) {
                $attributeIds = array_map('intval', $data['configurable_attributes'] ?? []);
                $product->configurableAttributes()->sync($attributeIds);

                if (! empty($attributeIds)) {
                    $this->configurable->generateVariants($product, $attributeIds);
                }
            }
        });

        $this->auditLogger->log('product.updated', $product, "Producto {$product->sku} actualizado");

        return to_route('admin.products.index')->with('success', 'Producto actualizado.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $sku = $product->sku;
        $product->media()->detach();
        $product->delete();

        $this->auditLogger->log('product.deleted', null, "Producto {$sku} eliminado");

        return to_route('admin.products.index')->with('success', 'Producto eliminado.');
    }

    /**
     * Precio base + disponibilidad/overrides por tienda + galería.
     *
     * @param  array<string, mixed>  $data
     */
    private function persistRelations(Product $product, array $data): void
    {
        $product->prices()->updateOrCreate(
            ['store_id' => null],
            $this->priceAttributes($data),
        );

        foreach ($data['stores'] ?? [] as $storeData) {
            $storeId = (int) $storeData['store_id'];

            $product->storeLinks()->updateOrCreate(
                ['store_id' => $storeId],
                ['is_active' => $storeData['is_active'] ?? false],
            );

            if (isset($storeData['price']) && $storeData['price'] !== '' && $storeData['price'] !== null) {
                $product->prices()->updateOrCreate(['store_id' => $storeId], $this->priceAttributes($storeData));
            } else {
                $product->prices()->where('store_id', $storeId)->delete();
            }
        }

        $product->syncMediaCollection(array_map('intval', $data['media'] ?? []), 'gallery');

        $product->categories()->sync(array_map('intval', $data['categories'] ?? []));

        $product->labels()->sync(array_map('intval', $data['labels'] ?? []));

        $this->persistAttributeValues($product, $data['attribute_values'] ?? []);
    }

    /**
     * Reemplaza los componentes del bundle con los enviados (product_id + cantidad).
     *
     * @param  list<array{product_id: int|string, quantity: int|string}>  $items
     */
    private function persistBundleItems(Product $product, array $items): void
    {
        $keep = [];
        $sort = 0;

        foreach ($items as $row) {
            $componentId = (int) ($row['product_id'] ?? 0);

            // Un bundle no puede contenerse a sí mismo.
            if ($componentId === 0 || $componentId === $product->id) {
                continue;
            }

            $product->bundleItems()->updateOrCreate(
                ['product_id' => $componentId],
                ['quantity' => max(1, (int) ($row['quantity'] ?? 1)), 'sort_order' => $sort++],
            );

            $keep[] = $componentId;
        }

        $product->bundleItems()->whereNotIn('product_id', $keep ?: [0])->delete();
    }

    /**
     * Reemplaza los enlaces descargables con los enviados (subidos previamente).
     *
     * @param  list<array{id?: int|string, title?: string, file_path?: string, original_name?: ?string, max_downloads?: int|string|null}>  $links
     */
    private function persistDownloadableLinks(Product $product, array $links): void
    {
        $keep = [];
        $sort = 0;

        foreach ($links as $row) {
            if (empty($row['file_path']) || empty($row['title'])) {
                continue;
            }

            $max = $row['max_downloads'] ?? null;

            $attributes = [
                'title' => $row['title'],
                'file_path' => $row['file_path'],
                'original_name' => $row['original_name'] ?? null,
                'max_downloads' => ($max === '' || $max === null) ? null : (int) $max,
                'sort_order' => $sort++,
            ];

            $existing = ! empty($row['id'])
                ? $product->downloadableLinks()->whereKey($row['id'])->first()
                : null;

            $link = $existing
                ? tap($existing)->update($attributes)
                : $product->downloadableLinks()->create($attributes);

            $keep[] = $link->id;
        }

        $product->downloadableLinks()->whereNotIn('id', $keep ?: [0])->delete();
    }

    /**
     * Guarda los valores de atributo del producto. Multiselect se serializa a JSON.
     *
     * @param  array<int|string, mixed>  $values
     */
    private function persistAttributeValues(Product $product, array $values): void
    {
        $attributes = Attribute::whereIn('id', array_keys($values))->get()->keyBy('id');

        foreach ($values as $attributeId => $raw) {
            $attribute = $attributes->get((int) $attributeId);

            if (! $attribute) {
                continue;
            }

            $value = is_array($raw)
                ? json_encode(array_values(array_filter($raw, fn ($v) => $v !== '' && $v !== null)))
                : $raw;

            if ($value === null || $value === '' || $value === '[]') {
                $product->attributeValues()->where('attribute_id', $attributeId)->delete();

                continue;
            }

            $product->attributeValues()->updateOrCreate(
                ['attribute_id' => (int) $attributeId],
                ['value' => $value],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function priceAttributes(array $data): array
    {
        return [
            'price' => $data['price'] ?? 0,
            'special_price' => $data['special_price'] ?? null,
            'special_price_from' => $data['special_price_from'] ?? null,
            'special_price_to' => $data['special_price_to'] ?? null,
        ];
    }

    private function uniqueSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $name);
        $candidate = $base;
        $counter = 2;

        while (Product::where('slug', $candidate)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $candidate = "{$base}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array<int|string, mixed>  $attrs
     * @param  Collection<int, Attribute>  $filterableAttributes
     */
    private function applyAttributeFilters(Builder $query, array $attrs, Collection $filterableAttributes): void
    {
        $attributes = $filterableAttributes->keyBy('id');

        foreach ($attrs as $attributeId => $raw) {
            $attribute = $attributes->get((int) $attributeId);

            if (! $attribute || ! $this->hasAttributeFilterValue($attribute, $raw)) {
                continue;
            }

            $query->whereHas('attributeValues', function (Builder $q) use ($attribute, $raw) {
                $q->where('attribute_id', $attribute->id);

                match ($attribute->type) {
                    Attribute::TYPE_NUMBER => $this->applyNumberAttributeFilter($q, is_array($raw) ? $raw : []),
                    Attribute::TYPE_DATE => $this->applyDateAttributeFilter($q, is_array($raw) ? $raw : []),
                    Attribute::TYPE_BOOLEAN => $raw === '' || $raw === null ? null : $q->where('value', (string) $raw),
                    Attribute::TYPE_MULTISELECT => $raw === '' || $raw === null ? null : $q->where('value', 'like', '%"'.addcslashes((string) $raw, '%_\\').'"%'),
                    default => $raw === '' || $raw === null ? null : $q->where('value', 'like', '%'.addcslashes((string) $raw, '%_\\').'%'),
                };
            });
        }
    }

    private function hasAttributeFilterValue(Attribute $attribute, mixed $raw): bool
    {
        if ($attribute->type === Attribute::TYPE_NUMBER) {
            return is_array($raw) && (($raw['min'] ?? '') !== '' || ($raw['max'] ?? '') !== '');
        }

        if ($attribute->type === Attribute::TYPE_DATE) {
            return is_array($raw) && (($raw['from'] ?? '') !== '' || ($raw['to'] ?? '') !== '');
        }

        return $raw !== '' && $raw !== null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function applyNumberAttributeFilter(Builder $query, array $raw): void
    {
        if (($raw['min'] ?? '') !== '') {
            $query->whereRaw('CAST(value AS DECIMAL(12, 4)) >= ?', [$raw['min']]);
        }

        if (($raw['max'] ?? '') !== '') {
            $query->whereRaw('CAST(value AS DECIMAL(12, 4)) <= ?', [$raw['max']]);
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function applyDateAttributeFilter(Builder $query, array $raw): void
    {
        if (($raw['from'] ?? '') !== '') {
            $query->where('value', '>=', $raw['from']);
        }

        if (($raw['to'] ?? '') !== '') {
            $query->where('value', '<=', $raw['to']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function productGridRow(Product $product): array
    {
        $attributes = $product->attributeValues
            ->mapWithKeys(fn ($value) => [
                $value->attribute_id => [
                    'raw' => $this->attributeRawValue((string) $value->value),
                    'label' => $this->attributeDisplayValue($value->attribute, (string) $value->value),
                ],
            ]);

        return [
            'id' => $product->id,
            'type' => $product->type,
            'sku' => $product->sku,
            'name' => $product->name,
            'status' => $product->status,
            'visibility' => $product->visibility,
            'price' => match (true) {
                $product->isConfigurable() => $this->configurable->lowestVariantBasePrice($product),
                $product->isBundle() => $this->bundles->priceFor($product)['effective_price'],
                default => $this->pricing->priceFor($product)['effective_price'],
            },
            'stock' => [
                'available' => $product->totalAvailableQty(),
                'status' => $product->totalAvailableQty() > 0 ? 'in' : 'out',
            ],
            'thumbnail' => $product->primaryMedia('gallery')?->url,
            'categories' => $product->categories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])->values(),
            'stores' => $product->storeLinks->map(fn ($link) => [
                'id' => $link->store_id,
                'name' => $link->store?->name,
                'code' => $link->store?->code,
                'is_active' => $link->is_active,
            ])->values(),
            'labels' => $product->labels->map(fn (ProductLabel $label) => [
                'id' => $label->id,
                'text' => $label->text,
                'text_color' => $label->text_color,
                'background_color' => $label->background_color,
            ])->values(),
            'attributes' => $attributes,
        ];
    }

    private function attributeDisplayValue(?Attribute $attribute, string $value): string
    {
        if (! $attribute) {
            return $value;
        }

        if ($attribute->type === Attribute::TYPE_BOOLEAN) {
            return $value === '1' ? 'Sí' : 'No';
        }

        if ($attribute->type === Attribute::TYPE_MULTISELECT) {
            $values = json_decode($value, true);

            if (! is_array($values)) {
                return $value;
            }

            return collect($values)
                ->map(fn ($optionValue) => $attribute->options->firstWhere('value', $optionValue)?->label ?? $optionValue)
                ->implode(', ');
        }

        if ($attribute->type === Attribute::TYPE_SELECT) {
            return $attribute->options->firstWhere('value', $value)?->label ?? $value;
        }

        return $value;
    }

    /**
     * @return string|list<string>
     */
    private function attributeRawValue(string $value): string|array
    {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : $value;
    }

    /**
     * @param  Collection<int, Attribute>  $filterableAttributes
     * @return array<string, mixed>
     */
    private function productGridFilterOptions(Collection $filterableAttributes): array
    {
        return [
            'statuses' => [
                ['value' => Product::STATUS_ACTIVE, 'label' => 'Activo'],
                ['value' => Product::STATUS_INACTIVE, 'label' => 'Inactivo'],
            ],
            'types' => [
                ['value' => Product::TYPE_SIMPLE, 'label' => 'Simple'],
                ['value' => Product::TYPE_CONFIGURABLE, 'label' => 'Configurable'],
                ['value' => Product::TYPE_BUNDLE, 'label' => 'Paquete'],
                ['value' => Product::TYPE_DOWNLOADABLE, 'label' => 'Descargable'],
            ],
            'visibilities' => [
                ['value' => 'both', 'label' => 'Catálogo y búsqueda'],
                ['value' => 'catalog', 'label' => 'Solo catálogo'],
                ['value' => 'search', 'label' => 'Solo búsqueda'],
                ['value' => 'hidden', 'label' => 'Oculto'],
            ],
            'categories' => Category::with('website:id,name')->orderBy('website_id')->orderBy('name')->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'label' => "{$category->website->name} / {$category->name}",
                ]),
            'stores' => Store::with('website:id,name')->orderBy('website_id')->orderBy('sort_order')->get()
                ->map(fn (Store $store) => [
                    'id' => $store->id,
                    'label' => "{$store->website->name} / {$store->name}",
                ]),
            'labels' => ProductLabel::active()->with('website:id,name')->orderBy('website_id')->orderBy('sort_order')->get()
                ->map(fn (ProductLabel $label) => [
                    'id' => $label->id,
                    'label' => $label->website ? "{$label->website->name} / {$label->text}" : $label->text,
                ]),
            'attributes' => $filterableAttributes->map(fn (Attribute $attribute) => [
                'id' => $attribute->id,
                'code' => $attribute->code,
                'name' => $attribute->name,
                'type' => $attribute->type,
                'options' => $attribute->options->map(fn ($option) => [
                    'label' => $option->label,
                    'value' => $option->value,
                ])->values(),
            ])->values(),
        ];
    }

    /**
     * @param  Collection<int, Attribute>  $visibleAttributes
     * @return list<array<string, mixed>>
     */
    private function productGridColumns(Collection $visibleAttributes): array
    {
        return [
            ...collect([
                ['key' => 'image', 'label' => 'Imagen', 'locked' => true],
                ['key' => 'sku', 'label' => 'SKU', 'locked' => true],
                ['key' => 'name', 'label' => 'Nombre', 'locked' => true],
                ['key' => 'type', 'label' => 'Tipo'],
                ['key' => 'status', 'label' => 'Estado'],
                ['key' => 'visibility', 'label' => 'Visibilidad'],
                ['key' => 'price', 'label' => 'Precio'],
                ['key' => 'stock', 'label' => 'Stock'],
                ['key' => 'categories', 'label' => 'Categorías'],
                ['key' => 'stores', 'label' => 'Tiendas'],
                ['key' => 'labels', 'label' => 'Etiquetas'],
            ])->all(),
            ...$visibleAttributes->map(fn (Attribute $attribute) => [
                'key' => "attr:{$attribute->id}",
                'label' => $attribute->name,
                'attribute_id' => $attribute->id,
                'type' => $attribute->type,
            ])->all(),
        ];
    }

    /**
     * Datos comunes para los formularios de crear/editar.
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'stores' => Store::with('website:id,name')->orderBy('website_id')->get()
                ->map(fn (Store $store) => [
                    'id' => $store->id,
                    'label' => "{$store->website->name} / {$store->name}",
                ]),
            'availableImages' => Media::where('is_image', true)->latest()->limit(60)->get()
                ->map(fn (Media $media) => ['id' => $media->id, 'url' => $media->url, 'name' => $media->name]),
            'categories' => Category::with('website:id,name')->orderBy('website_id')->orderBy('name')->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'label' => "{$category->website->name} / {$category->name}",
                ]),
            'labels' => ProductLabel::active()->with('website:id,name')->orderBy('website_id')->orderBy('sort_order')->get()
                ->map(fn (ProductLabel $label) => [
                    'id' => $label->id,
                    'text' => $label->text,
                    'text_color' => $label->text_color,
                    'background_color' => $label->background_color,
                    'website' => $label->website?->name,
                ]),
            'attributes' => Attribute::with('options')->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (Attribute $attribute) => [
                    'id' => $attribute->id,
                    'code' => $attribute->code,
                    'name' => $attribute->name,
                    'type' => $attribute->type,
                    'is_required' => $attribute->is_required,
                    'is_configurable' => $attribute->is_configurable,
                    'options' => $attribute->options->map(fn ($option) => [
                        'label' => $option->label,
                        'value' => $option->value,
                    ])->values(),
                ]),
            'configurableAttributes' => Attribute::with('options')
                ->where('is_configurable', true)
                ->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (Attribute $attribute) => [
                    'id' => $attribute->id,
                    'code' => $attribute->code,
                    'name' => $attribute->name,
                    'type' => $attribute->type,
                    'options' => $attribute->options->map(fn ($option) => [
                        'label' => $option->label,
                        'value' => $option->value,
                    ])->values(),
                ]),
            // Productos que pueden ser componentes de un bundle (simples).
            'componentProducts' => Product::query()
                ->whereNull('parent_id')
                ->where('type', Product::TYPE_SIMPLE)
                ->orderBy('name')
                ->limit(300)
                ->get(['id', 'sku', 'name'])
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                ]),
        ];
    }
}
