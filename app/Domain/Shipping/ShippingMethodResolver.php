<?php

namespace App\Domain\Shipping;

use App\Models\Store;
use App\Models\StoreShippingMethod;
use Illuminate\Support\Collection;

/**
 * Determina qué métodos de envío están disponibles para una tienda dado el
 * subtotal y el país de destino, aplicando las restricciones configuradas.
 */
class ShippingMethodResolver
{
    /**
     * @return Collection<int, StoreShippingMethod>
     */
    public function availableForCart(Store $store, float $subtotal, ?string $country = null): Collection
    {
        return StoreShippingMethod::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->whereHas('method', fn ($q) => $q->where('is_active', true))
            ->with(['method', 'rates'])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (StoreShippingMethod $ssm) => $this->passesRestrictions($ssm, $subtotal, $country))
            ->values();
    }

    private function passesRestrictions(StoreShippingMethod $ssm, float $subtotal, ?string $country): bool
    {
        if ($ssm->min_subtotal !== null && $subtotal < (float) $ssm->min_subtotal) {
            return false;
        }

        if ($ssm->max_subtotal !== null && $subtotal > (float) $ssm->max_subtotal) {
            return false;
        }

        $countries = $ssm->countries;

        if (is_array($countries) && count($countries) > 0) {
            // Restringido por país: requiere un país de destino que esté en la lista.
            return $country !== null && in_array(strtoupper($country), array_map('strtoupper', $countries), true);
        }

        return true;
    }
}
