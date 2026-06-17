<?php

namespace App\Domain\Store;

use App\Domain\Catalog\ProductPricingService;
use App\Domain\Inventory\StockAvailabilityChecker;
use App\Models\Category;
use App\Models\HeaderMenuItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\StorefrontPage;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class HeaderMenuService
{
    private const MAX_PRODUCTS = 6;

    public function __construct(
        private readonly StockAvailabilityChecker $availability,
        private readonly ProductPricingService $pricing,
    ) {}

    /**
     * Construye el árbol completo del menú para una tienda, cargando
     * productos activos en las categorías que tienen expand_products = true.
     *
     * @return list<array<string, mixed>>
     */
    public function buildTree(Store $store): array
    {
        $items = HeaderMenuItem::query()
            ->where('store_id', $store->id)
            ->with(['category', 'product', 'page'])
            ->orderBy('sort_order')
            ->get();

        return $this->buildBranch($items, null, $store);
    }

    /**
     * Arbol editable del admin: solo items reales de BD, incluyendo inactivos.
     *
     * @return list<array<string, mixed>>
     */
    public function buildAdminTree(Store $store): array
    {
        $items = HeaderMenuItem::query()
            ->where('store_id', $store->id)
            ->with(['category:id,name', 'product:id,name,sku', 'page:id,title,slug'])
            ->orderBy('sort_order')
            ->get();

        return $this->buildAdminBranch($items, null);
    }

    /**
     * @param  Collection<int, HeaderMenuItem>  $items
     * @return list<array<string, mixed>>
     */
    private function buildBranch(Collection $items, ?int $parentId, Store $store): array
    {
        return $items
            ->where('parent_id', $parentId)
            ->where('is_active', true)
            ->values()
            ->map(fn (HeaderMenuItem $item) => $this->serializeItem($item, $items, $store))
            ->all();
    }

    /**
     * @param  Collection<int, HeaderMenuItem>  $items
     * @return list<array<string, mixed>>
     */
    private function buildAdminBranch(Collection $items, ?int $parentId): array
    {
        return $items
            ->where('parent_id', $parentId)
            ->values()
            ->map(fn (HeaderMenuItem $item) => [
                'id' => $item->id,
                'store_id' => $item->store_id,
                'parent_id' => $item->parent_id,
                'type' => $item->type,
                'label' => $item->label,
                'url' => $item->url,
                'category_id' => $item->category_id,
                'product_id' => $item->product_id,
                'page_id' => $item->page_id,
                'is_active' => $item->is_active,
                'expand_products' => $item->expand_products,
                'children' => $this->buildAdminBranch($items, $item->id),
                'products' => [],
            ])
            ->all();
    }

    /**
     * @param  Collection<int, HeaderMenuItem>  $allItems
     * @return array<string, mixed>
     */
    private function serializeItem(HeaderMenuItem $item, Collection $allItems, Store $store): array
    {
        $data = [
            'id' => $item->id,
            'type' => $item->type,
            'label' => $item->label,
            'url' => $this->resolveUrl($item, $store),
            'expand_products' => $item->expand_products,
            'children' => $this->buildBranch($allItems, $item->id, $store),
            'products' => [],
        ];

        if ($item->type === HeaderMenuItem::TYPE_ALL_CATEGORIES) {
            $data['children'] = [
                ...$this->categoryBranch(null, $store, $item->expand_products),
                ...$data['children'],
            ];
        }

        if ($item->expand_products && $item->category_id) {
            $data['products'] = $this->categoryProducts($item->category_id, $store);
        }

        return $data;
    }

    private function resolveUrl(HeaderMenuItem $item, Store $store): ?string
    {
        return match ($item->type) {
            HeaderMenuItem::TYPE_LINK => $item->url,
            HeaderMenuItem::TYPE_ALL_CATEGORIES => $this->storefrontPath($store, '/'),
            HeaderMenuItem::TYPE_CATEGORY => $item->category
                ? $this->storefrontPath($store, "/c/{$item->category->slug}")
                : null,
            HeaderMenuItem::TYPE_PRODUCT => $item->product
                ? $this->storefrontPath($store, "/p/{$item->product->slug}")
                : null,
            HeaderMenuItem::TYPE_PAGE => $item->page
                ? $this->pageUrl($store, $item->page)
                : null,
            HeaderMenuItem::TYPE_CUSTOM => $item->url,
            default => $item->url,
        };
    }

    private function pageUrl(Store $store, StorefrontPage $page): string
    {
        return $this->storefrontPath($store, $page->slug === StorefrontPage::HOME ? '/' : "/{$page->slug}");
    }

    private function storefrontPath(Store $store, string $path): string
    {
        $prefix = app(StoreContext::class)->pathPrefix();
        $path = '/'.ltrim($path, '/');

        if ($prefix === '' || $prefix !== $store->code) {
            return $path;
        }

        return '/'.$prefix.($path === '/' ? '' : $path);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categoryBranch(?int $parentId, Store $store, bool $includeProducts): array
    {
        return Category::query()
            ->where('store_id', $store->id)
            ->where('parent_id', $parentId)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Category $category) => [
                'id' => "category:{$category->id}",
                'type' => HeaderMenuItem::TYPE_CATEGORY,
                'label' => $category->name,
                'url' => $this->storefrontPath($store, "/c/{$category->slug}"),
                'expand_products' => $includeProducts,
                'children' => $this->categoryBranch($category->id, $store, $includeProducts),
                'products' => $includeProducts ? $this->categoryProducts($category->id, $store) : [],
            ])
            ->all();
    }

    /**
     * Productos activos, visibles en la tienda, relacionados a la categoría.
     *
     * @return list<array<string, mixed>>
     */
    public function categoryProducts(int $categoryId, Store $store): array
    {
        return Product::query()
            ->select('products.id', 'products.slug', 'products.name', 'products.sku')
            ->active()
            ->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId))
            ->whereHas('storeLinks', fn ($q) => $q->where('store_id', $store->id)->where('is_active', true))
            ->with([
                'media' => fn (BelongsToMany $q) => $q->wherePivot('collection', 'default')->wherePivot('is_primary', true),
                'prices' => fn ($q) => $q->where(fn ($query) => $query
                    ->where('store_id', $store->id)
                    ->orWhereNull('store_id')),
                'inventoryStocks',
            ])
            ->orderBy('name')
            ->take(self::MAX_PRODUCTS)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'sku' => $product->sku,
                'url' => $this->storefrontPath($store, "/p/{$product->slug}"),
                'thumbnail' => $product->primaryMedia()?->url,
                'in_stock' => $this->availability->canFulfill($product, 1),
                'price' => $this->pricing->priceFor($product, $store->id),
            ])
            ->all();
    }
}
