<?php

namespace Database\Factories;

use App\Models\CatalogPriceRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogPriceRule>
 */
class CatalogPriceRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_id' => null,
            'category_id' => null,
            'name' => fake()->words(3, true),
            'description' => null,
            'action' => CatalogPriceRule::ACTION_PERCENT,
            'value' => 15,
            'priority' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }

    public function percent(float $percent): static
    {
        return $this->state(fn () => ['action' => CatalogPriceRule::ACTION_PERCENT, 'value' => $percent]);
    }

    public function fixedAmount(float $amount): static
    {
        return $this->state(fn () => ['action' => CatalogPriceRule::ACTION_FIXED_AMOUNT, 'value' => $amount]);
    }

    public function fixedPrice(float $price): static
    {
        return $this->state(fn () => ['action' => CatalogPriceRule::ACTION_FIXED_PRICE, 'value' => $price]);
    }
}
