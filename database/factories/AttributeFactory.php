<?php

namespace Database\Factories;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'code' => Str::slug($name, '_').'_'.fake()->unique()->numberBetween(1, 100000),
            'name' => ucfirst($name),
            'type' => Attribute::TYPE_TEXT,
            'is_required' => false,
            'is_filterable' => false,
            'is_visible' => true,
            'is_configurable' => false,
            'sort_order' => 0,
        ];
    }

    public function select(): static
    {
        return $this->state(fn () => ['type' => Attribute::TYPE_SELECT]);
    }

    public function multiselect(): static
    {
        return $this->state(fn () => ['type' => Attribute::TYPE_MULTISELECT]);
    }
}
