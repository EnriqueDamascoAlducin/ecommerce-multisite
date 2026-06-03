<?php

namespace Database\Factories;

use App\Models\InventorySource;
use App\Models\InventoryStock;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryStock>
 */
class InventoryStockFactory extends Factory
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
            'inventory_source_id' => InventorySource::factory(),
            'physical_qty' => fake()->numberBetween(0, 100),
            'reserved_qty' => 0,
            'manage_stock' => true,
            'allow_backorders' => false,
            'low_stock_threshold' => null,
        ];
    }
}
