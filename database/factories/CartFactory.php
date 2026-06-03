<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
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
            'customer_id' => null,
            'session_token' => Str::random(40),
            'status' => Cart::STATUS_ACTIVE,
            'currency' => 'MXN',
            'expires_at' => now()->addDays(30),
        ];
    }
}
