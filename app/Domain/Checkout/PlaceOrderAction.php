<?php

namespace App\Domain\Checkout;

use App\Domain\Cart\CartTotalsCalculator;
use App\Domain\Inventory\StockReservationService;
use App\Domain\Inventory\StockService;
use App\Models\Cart;
use App\Models\Order;
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
        $cart->loadMissing('items.product.inventoryStocks', 'store.website');

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

            $this->reserveStock($cart, $order);

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

            $hasRow = $product->inventoryStocks->firstWhere('inventory_source_id', $source->id);

            if ($hasRow !== null) {
                $this->reservations->reserve($product, $item->quantity, $reference, $source);
            }
        }
    }
}
