<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $website = Website::factory()->create();
        $store = Store::factory()->create(['website_id' => $website->id]);
        $order = Order::factory()->create([
            'website_id' => $website->id,
            'store_id' => $store->id,
        ]);

        return [
            'website_id' => $website->id,
            'store_id' => $store->id,
            'order_id' => $order->id,
            'number' => strtoupper(fake()->unique()->bothify('ENV-######')),
            'status' => Shipment::STATUS_PENDING,
            'total_qty' => 0,
        ];
    }
}
