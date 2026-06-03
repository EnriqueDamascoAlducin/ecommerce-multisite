<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\StoreDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreDomain>
 */
class StoreDomainFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'host' => fake()->unique()->domainName(),
            'is_primary' => false,
        ];
    }
}
