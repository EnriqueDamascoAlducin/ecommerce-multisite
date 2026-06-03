<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Store;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
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
            'number' => strtoupper(fake()->unique()->bothify('INV-######')),
            'status' => Invoice::STATUS_PENDING,
            'currency' => 'MXN',
            'subtotal' => $order->subtotal,
            'discount' => 0,
            'shipping_amount' => $order->shipping_amount,
            'tax' => 0,
            'total' => $order->total,
            'invoiced_at' => now(),
        ];
    }
}
