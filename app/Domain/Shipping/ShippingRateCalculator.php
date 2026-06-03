<?php

namespace App\Domain\Shipping;

use App\Models\ShippingMethod;
use App\Models\StoreShippingMethod;

/**
 * Calcula el costo de un método de envío para un subtotal dado.
 */
class ShippingRateCalculator
{
    public function amount(StoreShippingMethod $storeMethod, float $subtotal): string
    {
        $type = $storeMethod->method?->type;

        if ($type === ShippingMethod::TYPE_FREE_SHIPPING || $type === ShippingMethod::TYPE_PICKUP) {
            return $this->money(0);
        }

        // Envío gratis a partir de cierto subtotal.
        if ($storeMethod->free_over !== null && $subtotal >= (float) $storeMethod->free_over) {
            return $this->money(0);
        }

        $rates = $storeMethod->relationLoaded('rates') ? $storeMethod->rates : $storeMethod->rates()->get();

        $matched = $rates->first(fn ($rate) => $rate->matches($subtotal)) ?? $rates->first();

        return $this->money($matched ? (float) $matched->amount : 0);
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
