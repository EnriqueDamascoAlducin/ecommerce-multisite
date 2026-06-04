<?php

namespace App\Domain\Promotion;

use App\Models\CatalogPriceRule;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Collection;

/**
 * Aplica reglas de catálogo al precio de un producto: ajustes automáticos por
 * sitio/categoría y vigencia. Devuelve el mejor precio (más bajo) resultante o
 * null si ninguna regla aplica o no mejora el precio base.
 *
 * Las reglas vigentes y los websites por tienda se cachean por instancia para
 * evitar reconsultar en listados de catálogo.
 */
class CatalogRuleEvaluator
{
    /** @var Collection<int, CatalogPriceRule>|null */
    private ?Collection $rules = null;

    /** @var array<int, int|null> */
    private array $websiteByStore = [];

    public function adjustedPrice(Product $product, float $basePrice, ?int $storeId = null): ?float
    {
        if ($basePrice <= 0) {
            return null;
        }

        $rules = $this->rules();

        if ($rules->isEmpty()) {
            return null;
        }

        $websiteId = $this->websiteFor($storeId);
        $categoryIds = $rules->contains(fn (CatalogPriceRule $rule) => $rule->category_id !== null)
            ? $this->categoryIds($product)
            : [];

        $best = null;

        foreach ($rules as $rule) {
            if (! $rule->matchesWebsite($websiteId)) {
                continue;
            }

            if ($rule->category_id !== null && ! in_array($rule->category_id, $categoryIds, true)) {
                continue;
            }

            $price = $rule->applyTo($basePrice);

            if ($price < $basePrice) {
                $best = $best === null ? $price : min($best, $price);
            }
        }

        return $best;
    }

    /**
     * @return Collection<int, CatalogPriceRule>
     */
    private function rules(): Collection
    {
        return $this->rules ??= CatalogPriceRule::query()
            ->active()
            ->orderBy('priority')
            ->get()
            ->filter(fn (CatalogPriceRule $rule) => $rule->isWithinWindow())
            ->values();
    }

    private function websiteFor(?int $storeId): ?int
    {
        if ($storeId === null) {
            return null;
        }

        return $this->websiteByStore[$storeId] ??= Store::whereKey($storeId)->value('website_id');
    }

    /**
     * @return list<int>
     */
    private function categoryIds(Product $product): array
    {
        $product->loadMissing('categories');

        return $product->categories->pluck('id')->all();
    }
}
