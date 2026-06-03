<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\ConfigurableProductService;
use App\Domain\Catalog\ProductPricingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\Store;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductPricingService $pricing,
        private readonly ConfigurableProductService $configurable,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'type' => $request->string('type')->toString(),
        ];

        $products = Product::query()
            ->whereNull('parent_id')
            ->with(['prices', 'media'])
            ->when($filters['search'], fn ($query, $search) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"),
            ))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'type' => $product->type,
                'sku' => $product->sku,
                'name' => $product->name,
                'status' => $product->status,
                'price' => $product->isConfigurable()
                    ? $this->configurable->priceForConfigurable($product, 0)['effective_price'] ?? null
                    : $this->pricing->priceFor($product)['effective_price'],
                'thumbnail' => $product->primaryMedia('gallery')?->url,
            ]);

        return Inertia::render('admin/products/index', [
            'products' => $products,
            'filters' => $filters,
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

            return $product;
        });

        $this->auditLogger->log('product.created', $product, "Producto {$product->sku} creado");

        return to_route('admin.products.index')->with('success', 'Producto creado.');
    }

    public function edit(Product $product): Response
    {
        $product->load(['prices', 'storeLinks', 'media', 'categories', 'attributeValues', 'parent', 'configurableAttributes']);

        $base = $product->basePrice();

        $data = [
            'id' => $product->id,
            'type' => $product->type,
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

        $this->persistAttributeValues($product, $data['attribute_values'] ?? []);
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
        ];
    }
}
