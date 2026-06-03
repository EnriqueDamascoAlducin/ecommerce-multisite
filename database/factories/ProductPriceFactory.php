<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPrice>
 */
class ProductPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'store_id' => null,
            'price' => fake()->randomFloat(2, 50, 5000),
            'special_price' => null,
            'special_price_from' => null,
            'special_price_to' => null,
        ];
    }
}
