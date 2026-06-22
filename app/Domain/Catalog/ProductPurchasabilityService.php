<?php

namespace App\Domain\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ProductPurchasabilityService
{
    public function isPurchasable(Product $product, int $storeId): bool
    {
        if ($product->status !== Product::STATUS_ACTIVE) {
            return false;
        }

        $isActiveInStore = $product->storeLinks()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->exists();

        if (! $isActiveInStore) {
            return false;
        }

        if ($product->visibility === 'hidden' && ! $this->isPurchasableVariant($product, $storeId)) {
            return false;
        }

        return ! $product->isDownloadable() || $product->downloadableLinks()->exists();
    }

    private function isPurchasableVariant(Product $product, int $storeId): bool
    {
        if ($product->parent_id === null || $product->type !== Product::TYPE_SIMPLE) {
            return false;
        }

        return $product->parent()
            ->where('type', Product::TYPE_CONFIGURABLE)
            ->where('status', Product::STATUS_ACTIVE)
            ->where('visibility', '!=', 'hidden')
            ->whereHas('storeLinks', fn (Builder $query) => $query
                ->where('store_id', $storeId)
                ->where('is_active', true))
            ->exists();
    }
}
