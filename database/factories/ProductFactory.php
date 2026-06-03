<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'type' => Product::TYPE_SIMPLE,
            'sku' => strtoupper(Str::random(8)),
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 100000),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => Product::STATUS_ACTIVE,
            'visibility' => 'both',
            'weight' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => Product::STATUS_INACTIVE]);
    }
}
