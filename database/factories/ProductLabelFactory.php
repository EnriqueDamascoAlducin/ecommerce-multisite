<?php

namespace Database\Factories;

use App\Models\ProductLabel;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductLabel>
 */
class ProductLabelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'text' => fake()->randomElement(['Oferta', 'Nuevo', 'Top ventas', 'Edición limitada']),
            'text_color' => '#ffffff',
            'background_color' => fake()->hexColor(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
