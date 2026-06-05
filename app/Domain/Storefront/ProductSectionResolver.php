<?php

namespace App\Domain\Storefront;

use App\Models\Product;
use App\Models\StorefrontPageSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductSectionResolver
{
    /**
     * @return Collection<int, Product>
     */
    public function resolve(StorefrontPageSection $section, int $storeId, int $limit = 12): Collection
    {
        $ids = $section->settings['product_ids'] ?? [];

        if (! empty($ids) && is_array($ids)) {
            return $this->byIds($ids, $storeId, $limit);
        }

        return $this->fallback($storeId, $limit);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Product>
     */
    private function byIds(array $ids, int $storeId, int $limit): Collection
    {
        $preserveOrder = implode(',', array_map('intval', $ids));

        return Product::query()
            ->active()
            ->with(['prices', 'media', 'inventoryStocks', 'labels'])
            ->whereIn('id', $ids)
            ->whereHas('storeLinks', fn (Builder $q) => $q->where('store_id', $storeId)->where('is_active', true))
            ->when($preserveOrder, fn (Builder $q) => $q->orderByRaw("FIELD(id, {$preserveOrder})"))
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    private function fallback(int $storeId, int $limit): Collection
    {
        return Product::query()
            ->active()
            ->with(['prices', 'media', 'inventoryStocks', 'labels'])
            ->whereHas('storeLinks', fn (Builder $q) => $q->where('store_id', $storeId)->where('is_active', true))
            ->latest()
            ->limit($limit)
            ->get();
    }
}
