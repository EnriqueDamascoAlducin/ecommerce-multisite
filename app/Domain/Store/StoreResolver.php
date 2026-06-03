<?php

namespace App\Domain\Store;

use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\Website;
use Illuminate\Http\Request;

/**
 * Resuelve la tienda actual de forma híbrida:
 *  1. Por Host (store_domains) → determina la tienda "de entrada" y su website.
 *  2. Si el primer segmento de la ruta coincide con el code de otra tienda
 *     activa del mismo website, esa tienda gana (prefijo de ruta).
 *  3. Si el host no está registrado, cae al website por defecto.
 *
 * Ejemplos:
 *  - interferenciales.com.mx          → store principal (por dominio)
 *  - interferenciales.com.mx/sports   → store "sports" (por prefijo)
 *  - veterinaria.com.mx               → store de otro website (por dominio)
 */
class StoreResolver
{
    public function __construct(private readonly StoreContext $context) {}

    public function resolve(Request $request): ?Store
    {
        [$store, $pathPrefix] = $this->resolveStore($request);

        if ($store) {
            $this->context->set($store);
            $this->context->setPathPrefix($pathPrefix);
        }

        return $store;
    }

    /**
     * @return array{0: ?Store, 1: string} La tienda y su prefijo de ruta ('' si por dominio).
     */
    private function resolveStore(Request $request): array
    {
        $domain = StoreDomain::with('store.website')
            ->where('host', $request->getHost())
            ->first();

        $website = $domain?->store?->website ?? $this->defaultWebsite();

        if (! $website) {
            return [null, ''];
        }

        $entryStore = $domain?->store ?? $website->defaultStore();

        $segment = $request->segment(1);

        if ($segment) {
            $byPath = $website->stores()->active()->where('code', $segment)->first();

            if ($byPath) {
                return [$byPath, $segment];
            }
        }

        return [$entryStore, ''];
    }

    private function defaultWebsite(): ?Website
    {
        return Website::where('is_default', true)->first()
            ?? Website::orderBy('sort_order')->first();
    }
}
