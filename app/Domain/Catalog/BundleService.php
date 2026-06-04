<?php

namespace App\Domain\Catalog;

use App\Domain\Inventory\StockAvailabilityChecker;
use App\Models\BundleItem;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Resuelve precio, contenido y disponibilidad de un producto tipo bundle.
 *
 * - Precio "dynamic": suma del precio efectivo de cada componente × su cantidad.
 * - Precio "fixed": el bundle tiene su propio precio (como un producto simple),
 *   los componentes solo describen el contenido.
 */
class BundleService
{
    public function __construct(
        private readonly ProductPricingService $pricing,
        private readonly StockAvailabilityChecker $availability,
    ) {}

    /**
     * Precio del bundle con la misma forma que ProductPricingService::priceFor.
     *
     * @return array{price: string|null, special_price: string|null, effective_price: string|null, is_special: bool}
     */
    public function priceFor(Product $bundle, ?int $storeId = null): array
    {
        if (! $bundle->hasDynamicBundlePrice()) {
            return $this->pricing->priceFor($bundle, $storeId);
        }

        $regular = 0.0;
        $effective = 0.0;
        $priced = false;

        foreach ($this->items($bundle) as $item) {
            if (! $item->product) {
                continue;
            }

            $componentPrice = $this->pricing->priceFor($item->product, $storeId);

            if ($componentPrice['price'] === null) {
                continue;
            }

            $priced = true;
            $regular += (float) $componentPrice['price'] * $item->quantity;
            $effective += (float) $componentPrice['effective_price'] * $item->quantity;
        }

        if (! $priced) {
            return ['price' => null, 'special_price' => null, 'effective_price' => null, 'is_special' => false];
        }

        $isSpecial = $effective < $regular - 0.001;

        return [
            'price' => $this->money($regular),
            'special_price' => $isSpecial ? $this->money($effective) : null,
            'is_special' => $isSpecial,
            'effective_price' => $this->money($effective),
        ];
    }

    /**
     * Contenido del bundle para PDP y snapshot de la orden.
     *
     * @return list<array{product_id: int, sku: ?string, name: ?string, quantity: int}>
     */
    public function componentsFor(Product $bundle): array
    {
        return $this->items($bundle)
            ->map(fn (BundleItem $item) => [
                'product_id' => $item->product_id,
                'sku' => $item->product?->sku,
                'name' => $item->product?->name,
                'quantity' => $item->quantity,
            ])
            ->values()
            ->all();
    }

    /**
     * ¿Hay stock para surtir `$quantity` bundles? Cada componente debe poder
     * surtir su cantidad × la cantidad de bundles pedida.
     */
    public function canFulfill(Product $bundle, int $quantity): bool
    {
        $items = $this->items($bundle);

        if ($items->isEmpty()) {
            return false;
        }

        return $items->every(function (BundleItem $item) use ($quantity) {
            return $item->product
                && $this->availability->canFulfill($item->product, $item->quantity * $quantity);
        });
    }

    /**
     * @return Collection<int, BundleItem>
     */
    private function items(Product $bundle): Collection
    {
        $items = $bundle->relationLoaded('bundleItems')
            ? $bundle->bundleItems
            : $bundle->bundleItems()->with('product.prices', 'product.inventoryStocks')->get();

        return $items;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
