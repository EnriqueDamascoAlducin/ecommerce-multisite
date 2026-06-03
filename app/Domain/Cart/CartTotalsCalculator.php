<?php

namespace App\Domain\Cart;

use App\Domain\Shipping\ShippingService;
use App\Models\Cart;

/**
 * Calcula los totales del carrito en el backend (fuente de verdad).
 * Los descuentos quedan en 0 hasta la fase de cupones/reglas.
 */
class CartTotalsCalculator
{
    public function __construct(private readonly ShippingService $shipping) {}

    /**
     * @return array{items_count: int, subtotal: string, discount: string, shipping: string, total: string}
     */
    public function totals(Cart $cart): array
    {
        $items = $cart->relationLoaded('items') ? $cart->items : $cart->items()->get();

        $subtotal = $items->reduce(
            fn (float $carry, $item) => $carry + ((float) $item->unit_price * $item->quantity),
            0.0,
        );

        $discount = 0.0; // cupones / reglas de carrito: fases futuras.
        $shipping = (float) $this->shipping->amountForCart($cart);

        return [
            'items_count' => (int) $items->sum('quantity'),
            'subtotal' => $this->money($subtotal),
            'discount' => $this->money($discount),
            'shipping' => $this->money($shipping),
            'total' => $this->money($subtotal - $discount + $shipping),
        ];
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
