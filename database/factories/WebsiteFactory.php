<?php

namespace Database\Factories;

use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Website>
 */
class WebsiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'code' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 100000),
            'name' => $name,
            'is_default' => false,
            'sort_order' => 0,
        ];
    }
}
