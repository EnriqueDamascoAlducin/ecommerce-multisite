<?php

namespace Database\Factories;

use App\Models\ShippingMethod;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreShippingMethod>
 */
class StoreShippingMethodFactory extends Factory
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
            'shipping_method_id' => ShippingMethod::factory(),
            'label' => null,
            'is_active' => true,
            'sort_order' => 0,
            'free_over' => null,
            'min_subtotal' => null,
            'max_subtotal' => null,
            'countries' => null,
        ];
    }
}
