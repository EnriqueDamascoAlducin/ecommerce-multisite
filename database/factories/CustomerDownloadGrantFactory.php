<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerDownloadGrant;
use App\Models\DownloadableLink;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerDownloadGrant>
 */
class CustomerDownloadGrantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'customer_id' => Customer::factory(),
            'downloadable_link_id' => DownloadableLink::factory(),
            'title' => fake()->words(3, true),
            'max_downloads' => null,
            'downloads_used' => 0,
            'granted_at' => now(),
        ];
    }
}
