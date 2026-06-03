<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductStore;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductStore>
 */
class ProductStoreFactory extends Factory
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
            'store_id' => Store::factory(),
            'is_active' => true,
            'visibility' => null,
        ];
    }
}
