<?php

namespace App\Domain\Inventory;

use App\Models\InventorySource;
use App\Models\Product;

/**
 * Responde "¿hay suficiente stock?" respetando manage_stock y backorders.
 */
class StockAvailabilityChecker
{
    /**
     * Disponible en una fuente concreta (físico − reservado). Si no hay fila, 0.
     */
    public function availableFor(Product $product, ?InventorySource $source = null): int
    {
        $stock = $this->resolveStock($product, $source);

        return $stock?->available_qty ?? 0;
    }

    /**
     * Disponible total sumando todas las fuentes.
     */
    public function totalAvailable(Product $product): int
    {
        return (int) $product->inventoryStocks()->get()
            ->sum(fn ($stock) => $stock->available_qty);
    }

    /**
     * ¿Se puede surtir la cantidad pedida? Si la fuente no gestiona stock o
     * permite backorders, siempre es true.
     */
    public function isAvailable(Product $product, int $quantity, ?InventorySource $source = null): bool
    {
        $stock = $this->resolveStock($product, $source);

        if ($stock === null) {
            return false;
        }

        if (! $stock->manage_stock || $stock->allow_backorders) {
            return true;
        }

        return $stock->available_qty >= $quantity;
    }

    /**
     * Validación por SKU, útil para carrito/checkout.
     */
    public function isAvailableBySku(string $sku, int $quantity, ?InventorySource $source = null): bool
    {
        $product = Product::where('sku', $sku)->first();

        return $product !== null && $this->isAvailable($product, $quantity, $source);
    }

    /**
     * ¿Se puede surtir la cantidad sumando todas las fuentes? Pensado para el
     * carrito/checkout. Sin filas de inventario => no gestionado => disponible.
     */
    public function canFulfill(Product $product, int $quantity): bool
    {
        $stocks = $product->relationLoaded('inventoryStocks')
            ? $product->inventoryStocks
            : $product->inventoryStocks()->get();

        if ($stocks->isEmpty()) {
            return true;
        }

        if ($stocks->contains(fn ($stock) => ! $stock->manage_stock || $stock->allow_backorders)) {
            return true;
        }

        return $stocks->sum(fn ($stock) => $stock->available_qty) >= $quantity;
    }

    private function resolveStock(Product $product, ?InventorySource $source): ?\App\Models\InventoryStock
    {
        $sourceId = $source?->id ?? InventorySource::default()?->id;

        if ($sourceId === null) {
            return null;
        }

        return $product->inventoryStocks()
            ->where('inventory_source_id', $sourceId)
            ->first();
    }
}
