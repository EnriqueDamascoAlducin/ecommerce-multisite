<?php

namespace App\Observers;

use App\Domain\Storefront\StorefrontSeoService;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStore;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\StorefrontPage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class StorefrontSeoCacheObserver
{
    public function __construct(private readonly StorefrontSeoService $seo) {}

    public function saved(Model $model): void
    {
        $this->forgetAffectedStores($model);
    }

    public function deleting(Model $model): void
    {
        $this->forgetAffectedStores($model);
    }

    private function forgetAffectedStores(Model $model): void
    {
        $this->affectedStores($model)
            ->unique('id')
            ->each(fn (Store $store) => $this->seo->forget($store));
    }

    /**
     * @return Collection<int, Store>
     */
    private function affectedStores(Model $model): Collection
    {
        return match (true) {
            $model instanceof Category => $model->store_id
                ? Store::query()->whereKey($model->store_id)->get()
                : collect(),
            $model instanceof ProductStore => Store::query()->whereKey($model->store_id)->get(),
            $model instanceof Product => $model->storeLinks()
                ->with('store')
                ->get()
                ->pluck('store')
                ->filter()
                ->values(),
            $model instanceof StorefrontPage => $model->stores()->get(),
            $model instanceof StoreDomain => Store::query()->whereKey($model->store_id)->get(),
            $model instanceof Store => Store::query()->where('website_id', $model->website_id)->get(),
            default => collect(),
        };
    }
}
