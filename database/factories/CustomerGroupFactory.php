<?php

namespace Database\Factories;

use App\Models\CustomerGroup;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerGroup>
 */
class CustomerGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['General', 'Mayorista', 'VIP', 'Distribuidor']);

        return [
            'website_id' => Website::factory(),
            'name' => $name,
            'code' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->optional()->sentence(),
            'color' => fake()->hexColor(),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
