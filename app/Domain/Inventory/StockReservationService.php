<?php

namespace App\Domain\Inventory;

use App\Models\InventorySource;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Gestiona el stock reservado (carrito/checkout) sin tocar el físico hasta
 * que la reserva se consume. available = physical − reserved.
 */
class StockReservationService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly StockAvailabilityChecker $availability,
    ) {}

    /**
     * Reserva stock. Lanza InsufficientStockException si no hay disponible
     * (salvo que la fuente permita backorders o no gestione stock).
     */
    public function reserve(
        Product $product,
        int $quantity,
        ?string $reference = null,
        ?InventorySource $source = null,
        ?CarbonInterface $expiresAt = null,
    ): StockReservation {
        $source = $source ?? $this->stock->defaultSource();

        return DB::transaction(function () use ($product, $quantity, $reference, $source, $expiresAt) {
            $stock = InventoryStock::lockForUpdate()->firstOrCreate(
                ['product_id' => $product->id, 'inventory_source_id' => $source->id],
            );

            if (! $this->canReserve($stock, $quantity)) {
                throw InsufficientStockException::for($product->sku, $quantity, $stock->available_qty);
            }

            $stock->reserved_qty += $quantity;
            $stock->save();

            $reservation = StockReservation::create([
                'product_id' => $product->id,
                'inventory_source_id' => $source->id,
                'quantity' => $quantity,
                'reference' => $reference,
                'status' => StockReservation::STATUS_ACTIVE,
                'expires_at' => $expiresAt,
            ]);

            $this->stock->recordMovement($stock, StockMovement::TYPE_RESERVATION, $quantity, 'Reserva', $reference);

            return $reservation;
        });
    }

    /**
     * Libera una reserva activa: el stock vuelve a estar disponible.
     */
    public function release(StockReservation $reservation): void
    {
        if ($reservation->status !== StockReservation::STATUS_ACTIVE) {
            return;
        }

        DB::transaction(function () use ($reservation) {
            $stock = InventoryStock::lockForUpdate()
                ->where('product_id', $reservation->product_id)
                ->where('inventory_source_id', $reservation->inventory_source_id)
                ->first();

            if ($stock) {
                $stock->reserved_qty = max(0, $stock->reserved_qty - $reservation->quantity);
                $stock->save();

                $this->stock->recordMovement($stock, StockMovement::TYPE_RELEASE, -$reservation->quantity, 'Liberación de reserva', $reservation->reference);
            }

            $reservation->update(['status' => StockReservation::STATUS_RELEASED]);
        });
    }

    /**
     * Consume una reserva activa: la mercancía sale (baja físico y reservado).
     */
    public function consume(StockReservation $reservation): void
    {
        if ($reservation->status !== StockReservation::STATUS_ACTIVE) {
            return;
        }

        DB::transaction(function () use ($reservation) {
            $stock = InventoryStock::lockForUpdate()
                ->where('product_id', $reservation->product_id)
                ->where('inventory_source_id', $reservation->inventory_source_id)
                ->first();

            if ($stock) {
                $stock->physical_qty -= $reservation->quantity;
                $stock->reserved_qty = max(0, $stock->reserved_qty - $reservation->quantity);
                $stock->save();

                $this->stock->recordMovement($stock, StockMovement::TYPE_OUT, -$reservation->quantity, 'Salida por venta', $reservation->reference);
            }

            $reservation->update(['status' => StockReservation::STATUS_CONSUMED]);
        });
    }

    /**
     * Libera todas las reservas activas asociadas a una referencia (p. ej. un carrito).
     */
    public function releaseByReference(string $reference): int
    {
        $reservations = StockReservation::active()->where('reference', $reference)->get();

        foreach ($reservations as $reservation) {
            $this->release($reservation);
        }

        return $reservations->count();
    }

    private function canReserve(InventoryStock $stock, int $quantity): bool
    {
        if (! $stock->manage_stock || $stock->allow_backorders) {
            return true;
        }

        return $stock->available_qty >= $quantity;
    }
}
