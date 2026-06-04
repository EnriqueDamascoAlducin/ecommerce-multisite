<?php

namespace Database\Factories;

use App\Models\HeaderMenuItem;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeaderMenuItem>
 */
class HeaderMenuItemFactory extends Factory
{
    protected $model = HeaderMenuItem::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'type' => HeaderMenuItem::TYPE_LINK,
            'label' => $this->faker->word(),
            'url' => $this->faker->url(),
            'is_active' => true,
            'expand_products' => false,
            'sort_order' => 0,
        ];
    }

    public function link(): static
    {
        return $this->state(fn () => [
            'type' => HeaderMenuItem::TYPE_LINK,
            'url' => $this->faker->url(),
        ]);
    }

    public function category(): static
    {
        return $this->state(fn () => [
            'type' => HeaderMenuItem::TYPE_CATEGORY,
            'url' => null,
        ]);
    }

    public function custom(): static
    {
        return $this->state(fn () => [
            'type' => HeaderMenuItem::TYPE_CUSTOM,
            'url' => $this->faker->url(),
        ]);
    }

    public function expandProducts(): static
    {
        return $this->state(fn () => [
            'expand_products' => true,
        ]);
    }

    public function active(bool $active = true): static
    {
        return $this->state(fn () => ['is_active' => $active]);
    }
}
