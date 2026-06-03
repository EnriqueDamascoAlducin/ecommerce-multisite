<?php

namespace App\Domain\Inventory;

use App\Models\InventorySource;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Punto único de mutación del stock físico. Toda variación queda registrada
 * como un StockMovement para mantener el historial auditable.
 */
class StockService
{
    /**
     * Devuelve (creando si hace falta) la fila de stock de un producto en una fuente.
     */
    public function stockFor(Product $product, ?InventorySource $source = null): InventoryStock
    {
        $source = $source ?? $this->defaultSource();

        return InventoryStock::firstOrCreate(
            ['product_id' => $product->id, 'inventory_source_id' => $source->id],
        );
    }

    /**
     * Aplica un delta (positivo o negativo) al stock físico y registra el movimiento.
     */
    public function adjust(
        Product $product,
        int $delta,
        string $type = StockMovement::TYPE_ADJUSTMENT,
        ?InventorySource $source = null,
        ?string $reason = null,
        ?string $reference = null,
        ?User $user = null,
    ): InventoryStock {
        $source = $source ?? $this->defaultSource();

        return DB::transaction(function () use ($product, $delta, $type, $source, $reason, $reference, $user) {
            $stock = InventoryStock::lockForUpdate()->firstOrCreate(
                ['product_id' => $product->id, 'inventory_source_id' => $source->id],
            );

            $stock->physical_qty += $delta;
            $stock->save();

            $this->recordMovement($stock, $type, $delta, $reason, $reference, $user);

            return $stock;
        });
    }

    /**
     * Fija el stock físico a un valor absoluto (ajuste manual).
     */
    public function setPhysical(
        Product $product,
        int $quantity,
        ?InventorySource $source = null,
        ?string $reason = null,
        ?User $user = null,
    ): InventoryStock {
        $source = $source ?? $this->defaultSource();
        $stock = $this->stockFor($product, $source);
        $delta = $quantity - $stock->physical_qty;

        return $this->adjust($product, $delta, StockMovement::TYPE_ADJUSTMENT, $source, $reason, null, $user);
    }

    /**
     * Registra un movimiento de stock. El balance_after refleja el físico actual.
     */
    public function recordMovement(
        InventoryStock $stock,
        string $type,
        int $quantity,
        ?string $reason = null,
        ?string $reference = null,
        ?User $user = null,
    ): StockMovement {
        return StockMovement::create([
            'product_id' => $stock->product_id,
            'inventory_source_id' => $stock->inventory_source_id,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $stock->physical_qty,
            'reason' => $reason,
            'reference' => $reference,
            'user_id' => $user?->id,
        ]);
    }

    public function defaultSource(): InventorySource
    {
        return InventorySource::default() ?? InventorySource::create([
            'code' => 'default',
            'name' => 'Almacén principal',
            'is_default' => true,
        ]);
    }
}
