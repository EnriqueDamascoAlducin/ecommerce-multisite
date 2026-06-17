<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\ConfigurableProductService;
use App\Domain\Catalog\ProductPricingService;
use App\Domain\Inventory\StockAvailabilityChecker;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\ProductDetailResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductController extends ApiController
{
    public function __construct(
        private readonly ProductPricingService $pricing,
        private readonly StockAvailabilityChecker $availability,
        private readonly ConfigurableProductService $configurable,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $store = $this->resolveStore($request);

        $products = Product::query()
            ->with(['prices', 'media', 'inventoryStocks'])
            ->whereNull('parent_id')
            ->where('status', Product::STATUS_ACTIVE)
            ->whereIn('visibility', ['both', 'catalog'])
            ->whereHas('storeLinks', fn (Builder $q) => $q->where('store_id', $store->id)->where('is_active', true))
            ->when($request->string('search')->toString(), fn (Builder $q, $search) => $q->where(
                fn (Builder $w) => $w->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"),
            ))
            ->when($request->string('category')->toString(), fn (Builder $q, $slug) => $q->whereHas(
                'categories', fn (Builder $c) => $c->where('slug', $slug),
            ))
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 20), 50));

        $products->getCollection()->each(fn (Product $product) => $this->decorate($product, $store));

        return ProductResource::collection($products);
    }

    public function show(Request $request, string $slug): ProductDetailResource
    {
        $store = $this->resolveStore($request);

        $product = Product::query()
            ->with(['prices', 'media', 'categories', 'attributeValues.attribute.options', 'inventoryStocks', 'configurableAttributes.options'])
            ->whereNull('parent_id')
            ->where('slug', $slug)
            ->where('status', Product::STATUS_ACTIVE)
            ->where('visibility', '!=', 'hidden')
            ->whereHas('storeLinks', fn (Builder $q) => $q->where('store_id', $store->id)->where('is_active', true))
            ->first();

        if (! $product) {
            throw new NotFoundHttpException('Producto no encontrado.');
        }

        $this->decorate($product, $store);
        $this->decorateDetail($product, $store);

        return new ProductDetailResource($product);
    }

    /**
     * Inyecta los campos calculados por tienda usados en listados.
     */
    private function decorate(Product $product, Store $store): void
    {
        $product->catalog_price = $product->isConfigurable()
            ? $this->configurable->priceForConfigurable($product, $store->id)
            : $this->pricing->priceFor($product, $store->id);

        $product->catalog_in_stock = $product->isConfigurable()
            ? $this->configurableInStock($product, $store)
            : $this->availability->canFulfill($product, 1);

        $product->catalog_thumbnail = $product->primaryMedia('gallery')?->url;
    }

    /**
     * Inyecta galería, atributos, categorías y opciones configurables (detalle).
     */
    private function decorateDetail(Product $product, Store $store): void
    {
        $product->catalog_gallery = $product->mediaInCollection('gallery')
            ->map(fn ($media) => ['url' => $media->url, 'alt' => $media->alt ?? $product->name])
            ->values()
            ->all();

        $product->catalog_attributes = $product->attributeValues
            ->filter(fn ($value) => $value->attribute?->is_visible)
            ->map(fn ($value) => [
                'name' => $value->attribute->name,
                'value' => $this->attributeLabel($value),
            ])
            ->values()
            ->all();

        $product->catalog_categories = $product->categories
            ->where('store_id', $store->id)
            ->map(fn ($category) => ['name' => $category->name, 'slug' => $category->slug])
            ->values()
            ->all();

        $product->catalog_configurable_options = $product->isConfigurable()
            ? $this->configurable->getConfigurableOptions($product)
            : [];

        $product->catalog_variants = $product->isConfigurable()
            ? $this->variants($product, $store)
            : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function variants(Product $product, Store $store): array
    {
        return $product->variants()
            ->where('status', Product::STATUS_ACTIVE)
            ->whereHas('storeLinks', fn (Builder $q) => $q->where('store_id', $store->id)->where('is_active', true))
            ->with(['prices' => fn ($q) => $q->where('store_id', $store->id), 'inventoryStocks', 'attributeValues.attribute'])
            ->get()
            ->map(fn (Product $variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => (float) ($variant->prices->first()?->price ?? 0),
                'special_price' => $variant->prices->first()?->special_price ? (float) $variant->prices->first()->special_price : null,
                'options' => $variant->attributeValues->mapWithKeys(fn ($av) => [$av->attribute?->code => $av->value]),
                'in_stock' => $this->availability->canFulfill($variant, 1),
            ])
            ->all();
    }

    private function configurableInStock(Product $product, Store $store): bool
    {
        return $product->variants()
            ->where('status', Product::STATUS_ACTIVE)
            ->whereHas('storeLinks', fn (Builder $q) => $q->where('store_id', $store->id)->where('is_active', true))
            ->whereHas('inventoryStocks', fn (Builder $q) => $q->whereRaw('(physical_qty - reserved_qty) > 0'))
            ->exists();
    }

    private function attributeLabel(mixed $value): string
    {
        $attribute = $value->attribute;
        $raw = $value->value;

        return match ($attribute->type) {
            'multiselect' => collect(json_decode((string) $raw, true) ?: [])
                ->map(fn ($v) => $attribute->options->firstWhere('value', $v)?->label ?? $v)
                ->implode(', '),
            'select' => $attribute->options->firstWhere('value', $raw)?->label ?? (string) $raw,
            'boolean' => $raw ? 'Sí' : 'No',
            default => (string) $raw,
        };
    }
}
