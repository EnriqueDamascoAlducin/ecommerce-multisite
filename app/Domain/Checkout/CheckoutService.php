<?php

namespace App\Domain\Checkout;

use App\Domain\Cart\CartService;
use App\Domain\Cart\CartTotalsCalculator;
use App\Domain\Payment\PaymentGatewayRegistry;
use App\Domain\Shipping\ShippingService;
use App\Models\Cart;
use App\Models\Order;

/**
 * Orquesta el checkout: arma el resumen para la página y crea la orden
 * (validación final + reserva de stock vía PlaceOrderAction).
 */
class CheckoutService
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CartTotalsCalculator $totals,
        private readonly ShippingService $shipping,
        private readonly TotalsValidator $validator,
        private readonly PlaceOrderAction $placeOrder,
        private readonly PaymentGatewayRegistry $gateways,
    ) {}

    /**
     * Datos para la página de checkout (null si no hay carrito con ítems).
     *
     * @return array<string, mixed>|null
     */
    public function summary(): ?array
    {
        $cart = $this->cart->current(false);

        if (! $cart || $cart->items->isEmpty()) {
            return null;
        }

        $customer = auth('customer')->user();

        return [
            'items' => $cart->items->map(fn ($item) => [
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'line_total' => $item->line_total,
            ])->values(),
            'totals' => $this->totals->totals($cart),
            'shippingOptions' => $this->shipping->optionsForCart($cart),
            'selectedShipping' => $cart->shipping_method_code,
            'paymentMethods' => $this->gateways->methods(),
            'customer' => $customer ? ['name' => $customer->name, 'email' => $customer->email] : null,
            'addresses' => $customer
                ? $customer->addresses->map(fn ($a) => $a->only([
                    'id', 'label', 'first_name', 'last_name', 'company', 'phone',
                    'line1', 'line2', 'neighborhood', 'city', 'state', 'postal_code', 'country',
                    'is_default_shipping', 'is_default_billing',
                ]))->values()
                : [],
        ];
    }

    /**
     * Crea la orden a partir del carrito actual.
     *
     * @param  array<string, mixed>  $data
     */
    public function place(array $data): Order
    {
        $cart = $this->cart->current(false);

        if (! $cart || $cart->items->isEmpty()) {
            throw CheckoutException::emptyCart();
        }

        $customer = auth('customer')->user();

        if ($customer && $cart->customer_id !== $customer->id) {
            $cart->update(['customer_id' => $customer->id]);
            $cart->setRelation('customer', $customer);
        }

        // Selección de envío (valida disponibilidad; lanza CartException si no aplica).
        $this->cart->setShippingMethod($data['shipping_method_code'] ?? null);
        $cart = $this->cart->current(false);

        $this->validator->validate($cart);

        $data['shipping_method_label'] = $this->shippingLabel($cart, $data['shipping_method_code'] ?? null);

        if (empty($data['email']) && auth('customer')->check()) {
            $data['email'] = auth('customer')->user()->email;
        }

        return $this->placeOrder->execute($cart, $data);
    }

    private function shippingLabel(Cart $cart, ?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        $option = collect($this->shipping->optionsForCart($cart))->firstWhere('code', $code);

        return $option['label'] ?? null;
    }
}
