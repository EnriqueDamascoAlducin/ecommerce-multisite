<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\StorefrontPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorefrontPage>
 */
class StorefrontPageFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (StorefrontPage $page): void {
            $page->stores()->syncWithoutDetaching([$page->store_id]);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'slug' => StorefrontPage::HOME,
            'title' => 'Home',
            'is_published' => true,
        ];
    }
}
