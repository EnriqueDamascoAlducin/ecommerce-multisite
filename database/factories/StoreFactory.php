<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
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
            'website_id' => Website::factory(),
            'code' => str($name)->slug(),
            'name' => ucfirst($name),
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
