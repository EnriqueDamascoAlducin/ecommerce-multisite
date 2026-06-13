<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\BundleService;
use App\Domain\Catalog\ConfigurableProductService;
use App\Domain\Catalog\ProductPricingService;
use App\Domain\Inventory\StockAvailabilityChecker;
use App\Domain\Store\StoreContext;
use App\Domain\Storefront\StorefrontPagePresenter;
use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\StorefrontPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly StoreContext $context,
        private readonly ProductPricingService $pricing,
        private readonly ConfigurableProductService $configurable,
        private readonly BundleService $bundles,
        private readonly StockAvailabilityChecker $availability,
        private readonly StorefrontPagePresenter $pagePresenter,
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

        return $this->renderPage(StorefrontPage::HOME, $products->all());
    }

    public function page(string $slug): Response
    {
        if (! $this->context->hasStore()) {
            throw new NotFoundHttpException('Tienda no resuelta.');
        }

        if ($slug === StorefrontPage::HOME) {
            throw new NotFoundHttpException('Pagina no encontrada.');
        }

        return $this->renderPage($slug, []);
    }

    public function category(Request $request, string $slug): Response
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

        $filterableAttributes = $this->filterableAttributes();
        $attrs = $request->input('attrs', []);

        $query = $this->catalogQuery()
            ->whereHas('categories', fn (Builder $q) => $q->where('categories.id', $category->id));

        if (is_array($attrs) && $attrs !== []) {
            $this->applyAttributeFilters($query, $attrs, $filterableAttributes);
        }

        $sort = (string) $request->input('sort', 'relevance');
        $this->applyCatalogSort($query, $sort);

        $products = $query
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Product $product) => $this->productCard($product));

        return Inertia::render('storefront/category', [
            'category' => [
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
            ],
            'filters' => [
                'attrs' => is_array($attrs) ? $attrs : [],
            ],
            'filterOptions' => [
                'attributes' => $this->presentFilterableAttributes($filterableAttributes),
            ],
            'sort' => in_array($sort, ['price_asc', 'price_desc', 'newest'], true) ? $sort : 'relevance',
            'products' => $products,
        ]);
    }

    /**
     * Best-effort catalog sort. Price ordering uses the store-specific base
     * price (falling back to the global price); specials/configurable pricing
     * are not factored in.
     */
    private function applyCatalogSort(Builder $query, string $sort): void
    {
        if ($sort === 'newest') {
            $query->orderByDesc('products.created_at');

            return;
        }

        if ($sort === 'price_asc' || $sort === 'price_desc') {
            $storeId = $this->context->store()->id;

            $priceSubquery = ProductPrice::query()
                ->select('price')
                ->whereColumn('product_id', 'products.id')
                ->where(fn (Builder $q) => $q->where('store_id', $storeId)->orWhereNull('store_id'))
                ->orderByRaw('store_id IS NULL')
                ->limit(1);

            $query->orderBy($priceSubquery, $sort === 'price_desc' ? 'desc' : 'asc');

            return;
        }

        $query->orderBy('products.name');
    }

    public function product(string $slug): Response
    {
        if (! $this->context->hasStore()) {
            throw new NotFoundHttpException('Tienda no resuelta.');
        }

        $store = $this->context->store();

        $product = Product::query()
            ->with([
                'prices', 'media', 'categories', 'labels', 'attributeValues.attribute.options', 'inventoryStocks',
                'configurableAttributes.options',
                'bundleItems.product.prices', 'bundleItems.product.inventoryStocks',
            ])
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
            'price' => match (true) {
                $product->isConfigurable() => $this->configurable->priceForConfigurable($product, $store->id)
                    ?? ['price' => null, 'special_price' => null, 'effective_price' => null, 'is_special' => false],
                $product->isBundle() => $this->bundles->priceFor($product, $store->id),
                default => $this->pricing->priceFor($product, $store->id),
            },
            'in_stock' => match (true) {
                $product->isConfigurable() => $product->variants()->where('status', Product::STATUS_ACTIVE)
                    ->whereHas('storeLinks', fn (Builder $q) => $q->where('store_id', $store->id)->where('is_active', true))
                    ->whereHas('inventoryStocks', fn ($q) => $q->whereRaw('(physical_qty - reserved_qty) > 0'))
                    ->exists(),
                $product->isBundle() => $this->bundles->canFulfill($product, 1),
                default => $this->availability->canFulfill($product, 1),
            },
            'gallery' => $product->mediaInCollection('gallery')
                ->map(fn ($media) => ['url' => $media->url, 'alt' => $media->alt ?? $product->name])
                ->values(),
            'attributes' => $this->visibleAttributes($product),
            'categories' => $product->categories
                ->where('website_id', $websiteId)
                ->map(fn (Category $category) => ['name' => $category->name, 'slug' => $category->slug])
                ->values(),
            'labels' => $this->labelsFor($product),
            'upsell_products' => $this->relatedProductCards($product, Product::LINK_TYPE_UPSELL),
            'cross_sell_products' => $this->relatedProductCards($product, Product::LINK_TYPE_CROSS_SELL),
        ];

        if ($product->isBundle()) {
            $result['bundle_items'] = $product->bundleItems
                ->filter(fn ($item) => $item->product)
                ->map(fn ($item) => [
                    'name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'quantity' => $item->quantity,
                ])
                ->values();
        }

        if ($product->isConfigurable()) {
            $variants = $product->variants()
                ->where('status', Product::STATUS_ACTIVE)
                ->whereHas('storeLinks', fn (Builder $q) => $q->where('store_id', $store->id)->where('is_active', true))
                ->with(['prices' => fn ($q) => $q->where(fn ($query) => $query->where('store_id', $store->id)->orWhereNull('store_id')), 'inventoryStocks', 'media', 'attributeValues.attribute'])
                ->get()
                ->map(fn (Product $v) => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'price' => (float) ($v->prices->firstWhere('store_id', $store->id)?->price ?? $v->prices->firstWhere('store_id', null)?->price ?? 0),
                    'special_price' => $v->prices->firstWhere('store_id', $store->id)?->special_price
                        ? (float) $v->prices->firstWhere('store_id', $store->id)->special_price
                        : ($v->prices->firstWhere('store_id', null)?->special_price
                            ? (float) $v->prices->firstWhere('store_id', null)->special_price
                            : null),
                    'is_special' => $v->prices->firstWhere('store_id', $store->id)?->isSpecialActive()
                        ?? $v->prices->firstWhere('store_id', null)?->isSpecialActive()
                        ?? false,
                    'options' => $v->attributeValues->mapWithKeys(fn ($av) => [$av->attribute?->code => $av->value]),
                    'in_stock' => $this->availability->canFulfill($v, 1),
                    'gallery' => $v->mediaInCollection('gallery')
                        ->map(fn ($media) => ['url' => $media->url, 'alt' => $media->alt ?? $v->name])
                        ->values(),
                ]);

            $activeOptionsByCode = [];
            foreach ($variants as $variant) {
                foreach ($variant['options'] as $code => $value) {
                    $activeOptionsByCode[$code][] = $value;
                }
            }

            $configurableOptions = $this->configurable->getConfigurableOptions($product);
            foreach ($configurableOptions as $i => $attrOpts) {
                $code = $attrOpts['attribute']['code'];
                $activeValues = $activeOptionsByCode[$code] ?? [];
                $configurableOptions[$i]['options'] = array_values(array_filter(
                    $attrOpts['options'],
                    fn (array $opt) => in_array($opt['value'], $activeValues, true),
                ));
            }

            $result['configurable_options'] = $configurableOptions;
            $result['variants'] = $variants;
        }

        return Inertia::render('storefront/product', [
            'product' => $result,
        ]);
    }

    public function search(Request $request): Response
    {
        if (! $this->context->hasStore()) {
            throw new NotFoundHttpException('Tienda no resuelta.');
        }

        $storeId = $this->context->store()->id;

        $query = Product::query()
            ->with(['prices', 'media', 'inventoryStocks', 'labels', 'bundleItems.product.prices', 'bundleItems.product.inventoryStocks'])
            ->where('status', Product::STATUS_ACTIVE)
            ->whereIn('visibility', ['both', 'search'])
            ->whereHas('storeLinks', fn (Builder $q) => $q->where('store_id', $storeId)->where('is_active', true));

        $queryParam = $request->input('q');
        if ($queryParam) {
            $query->where(function (Builder $q) use ($queryParam) {
                $q->where('name', 'like', '%'.$queryParam.'%')
                    ->orWhere('sku', 'like', '%'.$queryParam.'%');
            });
        }

        $filterableAttributes = $this->filterableAttributes();

        $attrs = $request->input('attrs', []);
        if (is_array($attrs) && $attrs !== []) {
            $this->applyAttributeFilters($query, $attrs, $filterableAttributes);
        }

        $products = $query->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Product $product) => $this->productCard($product));

        return Inertia::render('storefront/search', [
            'filters' => [
                'q' => $request->input('q', ''),
                'attrs' => $attrs,
            ],
            'filterOptions' => [
                'attributes' => $this->presentFilterableAttributes($filterableAttributes),
            ],
            'products' => $products,
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
            ->with(['prices', 'media', 'inventoryStocks', 'labels', 'bundleItems.product.prices', 'bundleItems.product.inventoryStocks'])
            ->where('status', Product::STATUS_ACTIVE)
            ->whereIn('visibility', ['both', 'catalog'])
            ->whereHas('storeLinks', fn (Builder $q) => $q->where('store_id', $storeId)->where('is_active', true));
    }

    /**
     * @param  list<array<string, mixed>>  $featured
     */
    private function renderPage(string $slug, array $featured): Response
    {
        $page = StorefrontPage::query()
            ->where('store_id', $this->context->store()->id)
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with('sections')
            ->first();

        if (! $page && $slug !== StorefrontPage::HOME) {
            throw new NotFoundHttpException('Pagina no encontrada.');
        }

        $contentPage = $page ? $this->pagePresenter->present($page) : null;

        return Inertia::render('storefront/home', [
            'featured' => $featured,
            'contentPage' => $contentPage,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function productCard(Product $product): array
    {
        $storeId = $this->context->store()->id;

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
            'labels' => $this->labelsFor($product),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function relatedProductCards(Product $product, string $type): array
    {
        $storeId = $this->context->store()->id;
        $relation = $type === Product::LINK_TYPE_UPSELL
            ? $product->upsellProducts()
            : $product->crossSellProducts();

        return $relation
            ->with(['prices', 'media', 'inventoryStocks', 'labels', 'bundleItems.product.prices', 'bundleItems.product.inventoryStocks'])
            ->where('status', Product::STATUS_ACTIVE)
            ->whereIn('visibility', ['both', 'catalog'])
            ->whereHas('storeLinks', fn (Builder $q) => $q->where('store_id', $storeId)->where('is_active', true))
            ->limit(12)
            ->get()
            ->map(fn (Product $relatedProduct) => $this->productCard($relatedProduct))
            ->values()
            ->all();
    }

    /**
     * Etiquetas activas del producto para el website actual, ordenadas.
     *
     * @return list<array{text: string, text_color: string, background_color: string}>
     */
    private function labelsFor(Product $product): array
    {
        $websiteId = $this->context->website()?->id;

        return $product->labels
            ->where('is_active', true)
            ->where('website_id', $websiteId)
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

    /**
     * Aplica filtros por atributos a la consulta de productos.
     *
     * @param  array<string, mixed>  $attrs
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
                    Attribute::TYPE_MULTISELECT => $this->applyMultiselectAttributeFilter($q, $raw),
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

        if ($attribute->type === Attribute::TYPE_MULTISELECT) {
            return is_array($raw) ? collect($raw)->contains(fn ($value) => $value !== '' && $value !== null) : $raw !== '' && $raw !== null;
        }

        return $raw !== '' && $raw !== null;
    }

    /**
     * @return Collection<int, Attribute>
     */
    private function filterableAttributes(): Collection
    {
        return Attribute::with('options')
            ->where('is_filterable', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, Attribute>  $attributes
     * @return Collection<int, array{id: int, code: string, name: string, type: string, options: Collection<int, array{label: string, value: string}>|array<int, never>}>
     */
    private function presentFilterableAttributes(Collection $attributes): Collection
    {
        return $attributes->map(fn (Attribute $attr) => [
            'id' => $attr->id,
            'code' => $attr->code,
            'name' => $attr->name,
            'type' => $attr->type,
            'options' => $attr->hasOptions()
                ? $attr->options->map(fn ($opt) => [
                    'label' => $opt->label,
                    'value' => $opt->value,
                ])->values()
                : [],
        ])->values();
    }

    private function applyMultiselectAttributeFilter(Builder $query, mixed $raw): void
    {
        $values = is_array($raw) ? $raw : [$raw];
        $values = collect($values)
            ->filter(fn ($value) => $value !== '' && $value !== null)
            ->map(fn ($value) => (string) $value)
            ->values();

        if ($values->isEmpty()) {
            return;
        }

        $query->where(function (Builder $nested) use ($values) {
            foreach ($values as $value) {
                $nested->orWhere('value', 'like', '%"'.addcslashes($value, '%_\\').'"%');
            }
        });
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function applyNumberAttributeFilter(Builder $query, array $raw): void
    {
        if (($raw['min'] ?? '') !== '') {
            $query->whereRaw('CAST(value AS DECIMAL(12,4)) >= ?', [(float) $raw['min']]);
        }

        if (($raw['max'] ?? '') !== '') {
            $query->whereRaw('CAST(value AS DECIMAL(12,4)) <= ?', [(float) $raw['max']]);
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
}
