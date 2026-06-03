<?php

namespace App\Domain\Sales;

use App\Domain\Inventory\StockReservationService;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\StockMovement;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;

class CreateShipmentAction
{
    public function __construct(
        private readonly ShipmentNumberGenerator $numbers,
        private readonly StockReservationService $reservations,
    ) {}

    /**
     * @param  array<int, array{order_item_id: int, quantity: int}>  $items
     */
    public function execute(Order $order, array $items): Shipment
    {
        $order->loadMissing('items.product', 'store.website');

        return DB::transaction(function () use ($order, $items) {
            $totalQty = collect($items)->sum('quantity');

            $number = $this->numbers->next($order->website);

            $shipment = Shipment::create([
                'website_id' => $order->website_id,
                'store_id' => $order->store_id,
                'order_id' => $order->id,
                'number' => $number,
                'total_qty' => $totalQty,
            ]);

            foreach ($items as $entry) {
                $orderItem = $order->items->firstWhere('id', $entry['order_item_id']);

                abort_unless($orderItem, 422, 'Ítem de orden no encontrado.');

                $shipment->items()->create([
                    'order_item_id' => $orderItem->id,
                    'quantity' => $entry['quantity'],
                ]);

                $this->consumeStockReservation($order, $orderItem, $entry['quantity']);
            }

            $this->transitionOrderStatus($order);

            return $shipment;
        });
    }

    private function consumeStockReservation(Order $order, OrderItem $orderItem, int $quantity): void
    {
        $reservation = StockReservation::active()
            ->where('reference', "order:{$order->id}")
            ->where('product_id', $orderItem->product_id)
            ->first();

        if (! $reservation) {
            return;
        }

        $stock = InventoryStock::lockForUpdate()
            ->where('product_id', $orderItem->product_id)
            ->where('inventory_source_id', $reservation->inventory_source_id)
            ->first();

        if ($stock) {
            $stock->physical_qty -= $quantity;
            $stock->reserved_qty = max(0, $stock->reserved_qty - $quantity);
            $stock->save();

            StockMovement::create([
                'product_id' => $stock->product_id,
                'inventory_source_id' => $stock->inventory_source_id,
                'type' => StockMovement::TYPE_OUT,
                'quantity' => -$quantity,
                'balance_after' => $stock->physical_qty,
                'reason' => 'Salida por envío',
                'reference' => $reservation->reference,
            ]);
        }

        if ($reservation->quantity > $quantity) {
            $reservation->decrement('quantity', $quantity);
        } else {
            $reservation->update(['status' => StockReservation::STATUS_CONSUMED]);
        }
    }

    private function transitionOrderStatus(Order $order): void
    {
        $totalOrdered = $order->items->sum('quantity');
        $totalShipped = Shipment::where('order_id', $order->id)
            ->whereIn('status', [Shipment::STATUS_SHIPPED, Shipment::STATUS_DELIVERED, Shipment::STATUS_PENDING])
            ->join('shipment_items', 'shipments.id', '=', 'shipment_items.shipment_id')
            ->sum('shipment_items.quantity');

        if ($totalShipped >= $totalOrdered) {
            $order->transitionTo(Order::STATUS_SHIPPED, 'Envío completado.');
        } else {
            $order->transitionTo(Order::STATUS_PARTIALLY_SHIPPED, 'Envío parcial creado.');
        }
    }
}
