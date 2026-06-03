<?php

namespace App\Domain\Shipping;

use App\Models\Cart;
use App\Models\StoreShippingMethod;

/**
 * Fachada de envíos para el storefront: opciones disponibles para un carrito y
 * costo del método seleccionado. Integra resolver + calculadora.
 */
class ShippingService
{
    public function __construct(
        private readonly ShippingMethodResolver $resolver,
        private readonly ShippingRateCalculator $calculator,
    ) {}

    /**
     * Opciones de envío disponibles para el carrito, con su costo calculado.
     *
     * @return list<array{code: string, label: string, type: string, amount: string}>
     */
    public function optionsForCart(Cart $cart, ?string $country = null): array
    {
        $store = $cart->store;

        if (! $store) {
            return [];
        }

        $subtotal = $this->subtotal($cart);
        $country ??= $this->resolveCountry($cart);

        return $this->resolver->availableForCart($store, $subtotal, $country)
            ->map(fn (StoreShippingMethod $ssm) => [
                'code' => $ssm->method->code,
                'label' => $ssm->displayLabel(),
                'type' => $ssm->method->type,
                'amount' => $this->calculator->amount($ssm, $subtotal),
            ])
            ->all();
    }

    /**
     * Costo del método seleccionado en el carrito (0.00 si no hay/aplica).
     */
    public function amountForCart(Cart $cart, ?string $country = null): string
    {
        if (! $cart->shipping_method_code) {
            return '0.00';
        }

        $option = collect($this->optionsForCart($cart, $country))
            ->firstWhere('code', $cart->shipping_method_code);

        return $option['amount'] ?? '0.00';
    }

    /**
     * ¿El método indicado está disponible para el carrito?
     */
    public function isAvailable(Cart $cart, string $code, ?string $country = null): bool
    {
        return collect($this->optionsForCart($cart, $country))->contains('code', $code);
    }

    private function subtotal(Cart $cart): float
    {
        $items = $cart->relationLoaded('items') ? $cart->items : $cart->items()->get();

        return (float) $items->reduce(
            fn (float $carry, $item) => $carry + ((float) $item->unit_price * $item->quantity),
            0.0,
        );
    }

    private function resolveCountry(Cart $cart): ?string
    {
        return $cart->customer?->defaultShippingAddress()?->country;
    }
}
