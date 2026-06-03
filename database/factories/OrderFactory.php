<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Store;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
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

        $subtotal = fake()->randomFloat(2, 100, 2000);
        $shipping = (float) fake()->randomElement([0, 99]);

        return [
            'website_id' => $website->id,
            'store_id' => $store->id,
            'customer_id' => null,
            'number' => strtoupper(fake()->unique()->bothify('ORD-######')),
            'status' => Order::STATUS_PENDING_PAYMENT,
            'email' => fake()->safeEmail(),
            'currency' => 'MXN',
            'subtotal' => $subtotal,
            'discount' => 0,
            'shipping_amount' => $shipping,
            'tax' => 0,
            'total' => $subtotal + $shipping,
            'shipping_method_code' => 'flat_rate',
            'shipping_method_label' => 'Envío estándar',
            'payment_method' => 'offline',
            'placed_at' => now(),
        ];
    }
}
