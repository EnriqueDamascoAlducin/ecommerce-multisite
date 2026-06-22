<?php

namespace App\Domain\Checkout;

use App\Domain\Cart\CartTotalsCalculator;
use App\Domain\Inventory\StockReservationService;
use App\Domain\Inventory\StockService;
use App\Models\Cart;
use App\Models\CartPriceRule;
use App\Models\CustomerAddress;
use App\Models\InventorySource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Crea la orden a partir del carrito en una transacción: orden + ítems +
 * direcciones, reserva el stock y marca el carrito como convertido.
 */
class PlaceOrderAction
{
    public function __construct(
        private readonly CartTotalsCalculator $totals,
        private readonly OrderNumberGenerator $numbers,
        private readonly OrderDraftBuilder $builder,
        private readonly StockReservationService $reservations,
        private readonly StockService $stock,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Cart $cart, array $data): Order
    {
        $cart->loadMissing(
            'items.product.inventoryStocks',
            'items.product.bundleItems.product.inventoryStocks',
            'store.website',
        );

        return DB::transaction(function () use ($cart, $data) {
            $website = $cart->store->website;
            $totals = $this->totals->totals($cart);
            $number = $this->numbers->next($website);

            $draft = $this->builder->build($cart, $data, $number, $totals);

            $order = Order::create($draft['order']);
            $order->items()->createMany($draft['items']);

            foreach ($draft['addresses'] as $address) {
                $order->addresses()->create($address);
            }

            $this->saveCustomerAddress($cart, $data);

            $this->reserveStock($cart, $order);
            $this->consumeCoupon($cart);

            $order->statusHistories()->create([
                'from_status' => null,
                'to_status' => Order::STATUS_PENDING_PAYMENT,
                'comment' => 'Orden creada desde el checkout.',
            ]);

            $cart->update(['status' => Cart::STATUS_CONVERTED]);

            return $order;
        });
    }

    /**
     * Reserva stock por ítem solo cuando el producto tiene inventario en la
     * fuente por defecto (los productos sin inventario no se gestionan).
     */
    private function reserveStock(Cart $cart, Order $order): void
    {
        $source = $this->stock->defaultSource();
        $reference = "order:{$order->id}";

        foreach ($cart->items as $item) {
            $product = $item->product;

            if (! $product) {
                continue;
            }

            // Un bundle no tiene inventario propio: se reserva el de cada componente.
            if ($product->isBundle()) {
                foreach ($product->bundleItems as $bundleItem) {
                    $this->reserveIfManaged($bundleItem->product, $bundleItem->quantity * $item->quantity, $reference, $source);
                }

                continue;
            }

            $this->reserveIfManaged($product, $item->quantity, $reference, $source);
        }
    }

    /**
     * Reserva stock solo si el producto tiene fila de inventario en la fuente dada.
     */
    private function reserveIfManaged(?Product $product, int $quantity, string $reference, InventorySource $source): void
    {
        if (! $product) {
            return;
        }

        $hasRow = $product->inventoryStocks->firstWhere('inventory_source_id', $source->id);

        if ($hasRow !== null) {
            $this->reservations->reserve($product, $quantity, $reference, $source);
        }
    }

    /**
     * Incrementa el contador de usos del cupón aplicado al carrito (si lo hay).
     */
    private function consumeCoupon(Cart $cart): void
    {
        if (! $cart->coupon_code) {
            return;
        }

        CartPriceRule::where('coupon_code', $cart->coupon_code)
            ->where('is_active', true)
            ->get()
            ->each(fn (CartPriceRule $rule) => $rule->increment('times_used'));
    }

    /** @param array<string, mixed> $data */
    private function saveCustomerAddress(Cart $cart, array $data): void
    {
        if (! ($data['save_address'] ?? false) || ! $cart->customer_id) {
            return;
        }

        $hasAddresses = CustomerAddress::query()
            ->where('customer_id', $cart->customer_id)
            ->exists();
        $shipping = $data['shipping'];

        CustomerAddress::create([
            'customer_id' => $cart->customer_id,
            'label' => 'Dirección de compra',
            'first_name' => $shipping['first_name'],
            'last_name' => $shipping['last_name'],
            'company' => $shipping['company'] ?? null,
            'phone' => $shipping['phone'] ?? null,
            'line1' => $shipping['line1'],
            'line2' => $shipping['line2'] ?? null,
            'neighborhood' => $shipping['neighborhood'] ?? null,
            'city' => $shipping['city'],
            'state' => $shipping['state'],
            'postal_code' => $shipping['postal_code'],
            'country' => $shipping['country'] ?? 'MX',
            'is_default_shipping' => ! $hasAddresses,
            'is_default_billing' => ! $hasAddresses && (bool) ($data['billing_same'] ?? false),
        ]);
    }
}
