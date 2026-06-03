<?php

namespace Database\Factories;

use App\Models\InventorySource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventorySource>
 */
class InventorySourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'code' => Str::slug($name, '_').'_'.fake()->unique()->numberBetween(1, 100000),
            'name' => "Almacén {$name}",
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
