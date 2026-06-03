<?php

namespace App\Domain\Checkout;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderAddress;

/**
 * Arma (sin persistir) los datos de la orden a partir del carrito y los datos
 * del checkout: atributos de la orden, snapshot de ítems y direcciones.
 */
class OrderDraftBuilder
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array{items_count: int, subtotal: string, discount: string, shipping: string, total: string}  $totals
     * @return array{order: array<string, mixed>, items: list<array<string, mixed>>, addresses: list<array<string, mixed>>}
     */
    public function build(Cart $cart, array $data, string $number, array $totals): array
    {
        $website = $cart->store->website;

        $order = [
            'website_id' => $website->id,
            'store_id' => $cart->store_id,
            'customer_id' => $cart->customer_id,
            'number' => $number,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'email' => $data['email'],
            'currency' => $cart->currency ?? 'MXN',
            'subtotal' => $totals['subtotal'],
            'discount' => $totals['discount'],
            'shipping_amount' => $totals['shipping'],
            'tax' => '0.00',
            'total' => $totals['total'],
            'shipping_method_code' => $cart->shipping_method_code,
            'shipping_method_label' => $data['shipping_method_label'] ?? null,
            'payment_method' => $data['payment_method'],
            'placed_at' => now(),
        ];

        $items = $cart->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'sku' => $item->sku,
            'name' => $item->name,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'line_total' => number_format((float) $item->unit_price * $item->quantity, 2, '.', ''),
            'options' => null,
        ])->all();

        $addresses = [
            $this->address(OrderAddress::TYPE_SHIPPING, $data['shipping']),
            $this->address(OrderAddress::TYPE_BILLING, $data['billing'] ?? $data['shipping']),
        ];

        return ['order' => $order, 'items' => $items, 'addresses' => $addresses];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function address(string $type, array $fields): array
    {
        return [
            'type' => $type,
            'first_name' => $fields['first_name'],
            'last_name' => $fields['last_name'],
            'company' => $fields['company'] ?? null,
            'phone' => $fields['phone'] ?? null,
            'line1' => $fields['line1'],
            'line2' => $fields['line2'] ?? null,
            'city' => $fields['city'],
            'state' => $fields['state'],
            'postal_code' => $fields['postal_code'],
            'country' => $fields['country'] ?? 'MX',
        ];
    }
}
