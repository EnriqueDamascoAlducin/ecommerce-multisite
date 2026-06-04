<?php

namespace Database\Factories;

use App\Models\DownloadableLink;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DownloadableLink>
 */
class DownloadableLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'title' => fake()->words(3, true),
            'file_path' => 'files/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'max_downloads' => null,
            'sort_order' => 0,
        ];
    }
}
