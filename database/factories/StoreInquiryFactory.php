<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\StoreInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreInquiry>
 */
class StoreInquiryFactory extends Factory
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
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'interest_area' => 'Electrotherapy Equipment',
            'message' => fake()->paragraph(),
            'status' => StoreInquiry::STATUS_NEW,
        ];
    }
}
