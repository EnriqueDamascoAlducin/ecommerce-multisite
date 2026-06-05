<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\BundleService;
use App\Domain\Catalog\ConfigurableProductService;
use App\Domain\Catalog\ProductPricingService;
use App\Domain\Inventory\StockAvailabilityChecker;
use App\Domain\Store\StoreContext;
use App\Domain\Storefront\ProductSectionResolver;
use App\Domain\Storefront\StorefrontPagePresenter;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageSection;
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
        private readonly BundleService $bundles,
        private readonly StockAvailabilityChecker $availability,
        private readonly StorefrontPagePresenter $pagePresenter,
        private readonly ProductSectionResolver $productSectionResolver,
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
                $product->isConfigurable() => $this->configurable->priceForConfigurable($product, $store->id),
                $product->isBundle() => $this->bundles->priceFor($product, $store->id),
                default => $this->pricing->priceFor($product, $store->id),
            },
            'in_stock' => match (true) {
                $product->isConfigurable() => $product->variants()->where('status', Product::STATUS_ACTIVE)
                    ->whereHas('storeLinks', fn (Builder $q) => $q->where('store_id', $store->id)->where('is_active', true))
                    ->whereHas('inventoryStocks', fn ($q) => $q->where('available_qty', '>', 0))
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
            ->with(['prices', 'media', 'inventoryStocks', 'labels'])
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

        if ($page && $contentPage) {
            $contentPage['sections'] = array_map(
                fn (array $section) => $this->resolveSectionProducts($section, $featured),
                $contentPage['sections'],
            );
        }

        if ($page && $featured === [] && $page->sections->contains('type', StorefrontPageSection::TYPE_FEATURED_PRODUCTS)) {
            $fallbackSections = $page->sections->where('type', StorefrontPageSection::TYPE_FEATURED_PRODUCTS)
                ->filter(fn (StorefrontPageSection $s) => empty($s->settings['product_ids'] ?? []));

            if ($fallbackSections->isNotEmpty()) {
                $featured = $this->catalogQuery()
                    ->latest()
                    ->limit(12)
                    ->get()
                    ->map(fn (Product $product) => $this->productCard($product))
                    ->all();
            }
        }

        return Inertia::render('storefront/home', [
            'featured' => $featured,
            'contentPage' => $contentPage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  list<array<string, mixed>>  $featured
     * @return array<string, mixed>
     */
    private function resolveSectionProducts(array $section, array $featured): array
    {
        if ($section['type'] !== StorefrontPageSection::TYPE_FEATURED_PRODUCTS) {
            return $section;
        }

        $settings = $section['settings'];
        $ids = $settings['product_ids'] ?? [];

        if (empty($ids) || ! is_array($ids)) {
            return $section;
        }

        $products = $this->productSectionResolver
            ->resolve(
                StorefrontPageSection::make(['settings' => ['product_ids' => $ids]]),
                $this->context->store()->id,
            )
            ->map(fn (Product $product) => $this->productCard($product))
            ->all();

        $settings['products'] = $products;
        $section['settings'] = $settings;

        return $section;
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
            'labels' => $this->labelsFor($product),
        ];
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
}
