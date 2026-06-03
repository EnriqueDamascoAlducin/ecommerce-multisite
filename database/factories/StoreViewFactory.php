<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\StoreView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreView>
 */
class StoreViewFactory extends Factory
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
            'code' => fake()->unique()->lexify('view_????'),
            'name' => 'Vista',
            'locale' => 'es',
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
