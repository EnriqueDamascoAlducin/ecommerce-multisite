<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\ConfigurableProductService;
use App\Domain\Catalog\ProductPricingService;
use App\Domain\Inventory\StockAvailabilityChecker;
use App\Domain\Store\StoreContext;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly StoreContext $context,
        private readonly ProductPricingService $pricing,
        private readonly ConfigurableProductService $configurable,
        private readonly StockAvailabilityChecker $availability,
    ) {}

    public function home(): Response
    {
        if (! $this->context->hasStore()) {
            return Inertia::render('storefront/home', ['featured' => []]);
        }

        $products = $this->catalogQuery()
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (Product $product) => $this->productCard($product));

        return Inertia::render('storefront/home', [
            'featured' => $products,
        ]);
    }

    public function category(string $slug): Response
    {
        if (! $this->context->hasStore()) {
            throw new NotFoundHttpException('Tienda no resuelta.');
        }

        $website = $this->context->website();

        $category = Category::query()
            ->where('website_id', $website->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $category) {
            throw new NotFoundHttpException('Categoría no encontrada.');
        }

        $products = $this->catalogQuery()
            ->whereHas('categories', fn (Builder $q) => $q->where('categories.id', $category->id))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Product $product) => $this->productCard($product));

        return Inertia::render('storefront/category', [
            'category' => [
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
            ],
            'products' => $products,
        ]);
    }

    public function product(string $slug): Response
    {
        if (! $this->context->hasStore()) {
            throw new NotFoundHttpException('Tienda no resuelta.');
        }

        $store = $this->context->store();

        $product = Product::query()
            ->with(['prices', 'media', 'categories', 'attributeValues.attribute.options', 'inventoryStocks', 'configurableAttributes.options'])
            ->where('slug', $slug)
            ->where('status', Product::STATUS_ACTIVE)
            ->where('visibility', '!=', 'hidden')
            ->whereHas('storeLinks', fn (Builder $q) => $q->where('store_id', $store->id)->where('is_active', true))
            ->first();

        if (! $product) {
            throw new NotFoundHttpException('Producto no encontrado.');
        }

        $websiteId = $this->context->website()->id;

        $result = [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'type' => $product->type,
            'price' => $product->isConfigurable()
                ? $this->configurable->priceForConfigurable($product, $store->id)
                : $this->pricing->priceFor($product, $store->id),
            'in_stock' => $product->isConfigurable()
                ? $product->variants()->where('status', Product::STATUS_ACTIVE)
                    ->whereHas('storeLinks', fn (Builder $q) => $q->where('store_id', $store->id)->where('is_active', true))
                    ->whereHas('inventoryStocks', fn ($q) => $q->where('available_qty', '>', 0))
                    ->exists()
                : $this->availability->canFulfill($product, 1),
            'gallery' => $product->mediaInCollection('gallery')
                ->map(fn ($media) => ['url' => $media->url, 'alt' => $media->alt ?? $product->name])
                ->values(),
            'attributes' => $this->visibleAttributes($product),
            'categories' => $product->categories
                ->where('website_id', $websiteId)
                ->map(fn (Category $category) => ['name' => $category->name, 'slug' => $category->slug])
                ->values(),
        ];

        if ($product->isConfigurable()) {
            $result['configurable_options'] = $this->configurable->getConfigurableOptions($product);
            $result['variants'] = $product->variants()
                ->where('status', Product::STATUS_ACTIVE)
                ->whereHas('storeLinks', fn (Builder $q) => $q->where('store_id', $store->id)->where('is_active', true))
                ->with(['prices' => fn ($q) => $q->where('store_id', $store->id), 'inventoryStocks', 'media'])
                ->get()
                ->map(fn (Product $v) => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'price' => (float) ($v->prices->first()?->price ?? 0),
                    'special_price' => $v->prices->first()?->special_price ? (float) $v->prices->first()->special_price : null,
                    'is_special' => $v->prices->first()?->isSpecialActive() ?? false,
                    'options' => $v->attributeValues->mapWithKeys(fn ($av) => [$av->attribute?->code => $av->value]),
                    'in_stock' => $this->availability->canFulfill($v, 1),
                    'gallery' => $v->mediaInCollection('gallery')
                        ->map(fn ($media) => ['url' => $media->url, 'alt' => $media->alt ?? $v->name])
                        ->values(),
                ]);
        }

        return Inertia::render('storefront/product', [
            'product' => $result,
        ]);
    }

    /**
     * Query base de catálogo: productos activos y visibles en la tienda actual.
     *
     * @return Builder<Product>
     */
    private function catalogQuery(): Builder
    {
        $storeId = $this->context->store()->id;

        return Product::query()
            ->with(['prices', 'media', 'inventoryStocks'])
            ->where('status', Product::STATUS_ACTIVE)
            ->whereIn('visibility', ['both', 'catalog'])
            ->whereHas('storeLinks', fn (Builder $q) => $q->where('store_id', $storeId)->where('is_active', true));
    }

    /**
     * @return array<string, mixed>
     */
    private function productCard(Product $product): array
    {
        $storeId = $this->context->store()->id;

        return [
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $this->pricing->priceFor($product, $storeId),
            'thumbnail' => $product->primaryMedia('gallery')?->url,
            'in_stock' => $this->availability->canFulfill($product, 1),
        ];
    }

    /**
     * Valores de atributos visibles, con etiqueta legible para opciones.
     *
     * @return list<array{name: string, value: string}>
     */
    private function visibleAttributes(Product $product): array
    {
        return $product->attributeValues
            ->filter(fn ($value) => $value->attribute?->is_visible)
            ->map(function ($value) {
                $attribute = $value->attribute;
                $raw = $value->value;

                $label = match ($attribute->type) {
                    'multiselect' => collect(json_decode((string) $raw, true) ?: [])
                        ->map(fn ($v) => $attribute->options->firstWhere('value', $v)?->label ?? $v)
                        ->implode(', '),
                    'select' => $attribute->options->firstWhere('value', $raw)?->label ?? (string) $raw,
                    'boolean' => $raw ? 'Sí' : 'No',
                    default => (string) $raw,
                };

                return ['name' => $attribute->name, 'value' => $label];
            })
            ->values()
            ->all();
    }
}
