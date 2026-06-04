<?php

namespace App\Domain\Store;

use App\Models\HeaderMenuItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class HeaderMenuService
{
    private const MAX_PRODUCTS = 6;

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
            ->with(['category', 'product'])
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
            ->with(['category:id,name', 'product:id,name,sku'])
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
            'url' => $this->resolveUrl($item),
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
            $data['products'] = $this->categoryProducts($item->category_id, $store->id);
        }

        return $data;
    }

    private function resolveUrl(HeaderMenuItem $item): ?string
    {
        return match ($item->type) {
            HeaderMenuItem::TYPE_LINK => $item->url,
            HeaderMenuItem::TYPE_ALL_CATEGORIES => route('storefront.home', absolute: false),
            HeaderMenuItem::TYPE_CATEGORY => $item->category
                ? route('storefront.category', $item->category->slug, false)
                : null,
            HeaderMenuItem::TYPE_PRODUCT => $item->product
                ? route('storefront.product', $item->product->slug, false)
                : null,
            HeaderMenuItem::TYPE_CUSTOM => $item->url,
            default => $item->url,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categoryBranch(?int $parentId, Store $store, bool $includeProducts): array
    {
        return Category::query()
            ->where('website_id', $store->website_id)
            ->where('parent_id', $parentId)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Category $category) => [
                'id' => "category:{$category->id}",
                'type' => HeaderMenuItem::TYPE_CATEGORY,
                'label' => $category->name,
                'url' => route('storefront.category', $category->slug, false),
                'expand_products' => $includeProducts,
                'children' => $this->categoryBranch($category->id, $store, $includeProducts),
                'products' => $includeProducts ? $this->categoryProducts($category->id, $store->id) : [],
            ])
            ->all();
    }

    /**
     * Productos activos, visibles en la tienda, relacionados a la categoría.
     *
     * @return list<array<string, mixed>>
     */
    public function categoryProducts(int $categoryId, int $storeId): array
    {
        return Product::query()
            ->select('products.id', 'products.slug', 'products.name', 'products.sku')
            ->active()
            ->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId))
            ->whereHas('storeLinks', fn ($q) => $q->where('store_id', $storeId)->where('is_active', true))
            ->with([
                'media' => fn (BelongsToMany $q) => $q->wherePivot('collection', 'default')->wherePivot('is_primary', true),
                'prices' => fn ($q) => $q->where('store_id', $storeId),
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
                'url' => route('storefront.product', $product->slug, false),
                'thumbnail' => $product->primaryMedia()?->url,
                'in_stock' => $product->totalAvailableQty() > 0,
                'price' => [
                    'price' => $product->prices->first()?->price,
                    'special_price' => $product->prices->first()?->special_price,
                    'effective_price' => $product->prices->first()?->effectivePrice(),
                    'is_special' => $product->prices->first()?->isSpecialActive(),
                ],
            ])
            ->all();
    }
}
