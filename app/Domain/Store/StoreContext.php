<?php

namespace App\Domain\Store;

use App\Models\Store;
use App\Models\StoreView;
use App\Models\Website;

/**
 * Mantiene el sitio resuelto para la petición actual (website + store + store view).
 * Se registra como singleton y lo puebla el middleware ResolveStore.
 */
class StoreContext
{
    private ?Store $store = null;

    private ?Website $website = null;

    private ?StoreView $storeView = null;

    private string $pathPrefix = '';

    public function set(Store $store, ?StoreView $storeView = null): void
    {
        $this->store = $store;
        $this->website = $store->website;
        $this->storeView = $storeView
            ?? $store->views()->where('is_default', true)->first()
            ?? $store->views()->orderBy('sort_order')->first();
    }

    /**
     * Prefijo de ruta de la tienda actual cuando se resolvió por path (p. ej. "sports").
     * Cadena vacía cuando la tienda se resolvió por dominio.
     */
    public function setPathPrefix(string $prefix): void
    {
        $this->pathPrefix = $prefix;
    }

    public function pathPrefix(): string
    {
        return $this->pathPrefix;
    }

    public function store(): ?Store
    {
        return $this->store;
    }

    public function website(): ?Website
    {
        return $this->website;
    }

    public function storeView(): ?StoreView
    {
        return $this->storeView;
    }

    public function hasStore(): bool
    {
        return $this->store !== null;
    }
}
