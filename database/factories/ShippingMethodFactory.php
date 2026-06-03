<?php

namespace Database\Factories;

use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'code' => Str::slug($name, '_').'_'.fake()->unique()->numberBetween(1, 100000),
            'name' => ucfirst($name),
            'type' => ShippingMethod::TYPE_FLAT_RATE,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function freeShipping(): static
    {
        return $this->state(fn () => ['type' => ShippingMethod::TYPE_FREE_SHIPPING]);
    }

    public function pickup(): static
    {
        return $this->state(fn () => ['type' => ShippingMethod::TYPE_PICKUP]);
    }
}
