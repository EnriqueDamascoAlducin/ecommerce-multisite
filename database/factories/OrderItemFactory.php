<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 4);
        $price = fake()->randomFloat(2, 50, 500);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->bothify('SKU-####')),
            'name' => fake()->words(2, true),
            'quantity' => $quantity,
            'unit_price' => $price,
            'line_total' => $price * $quantity,
            'options' => null,
        ];
    }
}
