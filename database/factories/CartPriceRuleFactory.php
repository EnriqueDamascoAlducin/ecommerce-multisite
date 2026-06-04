<?php

namespace Database\Factories;

use App\Models\CartPriceRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartPriceRule>
 */
class CartPriceRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_id' => null,
            'name' => fake()->words(3, true),
            'description' => null,
            'coupon_code' => null,
            'action' => CartPriceRule::ACTION_PERCENT,
            'value' => 10,
            'min_subtotal' => null,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
            'usage_limit' => null,
            'times_used' => 0,
        ];
    }

    public function coupon(string $code): static
    {
        return $this->state(fn () => ['coupon_code' => $code]);
    }

    public function fixed(float $amount): static
    {
        return $this->state(fn () => ['action' => CartPriceRule::ACTION_FIXED, 'value' => $amount]);
    }

    public function percent(float $percent): static
    {
        return $this->state(fn () => ['action' => CartPriceRule::ACTION_PERCENT, 'value' => $percent]);
    }

    public function freeShipping(): static
    {
        return $this->state(fn () => ['action' => CartPriceRule::ACTION_FREE_SHIPPING, 'value' => 0]);
    }
}
