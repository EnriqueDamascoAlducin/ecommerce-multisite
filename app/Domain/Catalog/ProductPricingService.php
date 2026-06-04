<?php

namespace App\Domain\Catalog;

use App\Domain\Promotion\CatalogRuleEvaluator;
use App\Models\Product;
use App\Models\ProductPrice;

/**
 * Resuelve el precio efectivo de un producto para una tienda: usa el override
 * por tienda si existe, si no el precio base; aplica precio especial vigente y
 * las reglas de catálogo (descuentos automáticos por sitio/categoría).
 */
class ProductPricingService
{
    public function __construct(private readonly CatalogRuleEvaluator $catalogRules) {}

    public function priceRowFor(Product $product, ?int $storeId = null): ?ProductPrice
    {
        $prices = $product->relationLoaded('prices') ? $product->prices : $product->prices()->get();

        if ($storeId) {
            $override = $prices->firstWhere('store_id', $storeId);

            if ($override) {
                return $override;
            }
        }

        return $prices->firstWhere('store_id', null);
    }

    /**
     * @return array{price: string|null, special_price: string|null, effective_price: string|null, is_special: bool}
     */
    public function priceFor(Product $product, ?int $storeId = null): array
    {
        $row = $this->priceRowFor($product, $storeId);

        if (! $row) {
            return ['price' => null, 'special_price' => null, 'effective_price' => null, 'is_special' => false];
        }

        $regular = (float) $row->price;
        $current = (float) $row->effectivePrice(); // precio especial vigente, si aplica
        $rulePrice = $this->catalogRules->adjustedPrice($product, $regular, $storeId);

        $effective = $rulePrice !== null ? min($current, $rulePrice) : $current;
        $isSpecial = $effective < $regular - 0.001;

        return [
            'price' => (string) $row->price,
            'special_price' => $isSpecial
                ? $this->money($effective)
                : ($row->special_price !== null ? (string) $row->special_price : null),
            'is_special' => $isSpecial,
            'effective_price' => $isSpecial ? $this->money($effective) : (string) $row->price,
        ];
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
