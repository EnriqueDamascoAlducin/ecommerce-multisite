<?php

namespace Database\Factories;

use App\Models\StorefrontPage;
use App\Models\StorefrontPageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorefrontPageSection>
 */
class StorefrontPageSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'storefront_page_id' => StorefrontPage::factory(),
            'type' => StorefrontPageSection::TYPE_HERO,
            'sort_order' => 0,
            'is_active' => true,
            'settings' => [
                'eyebrow' => 'Campaign Launch',
                'title' => 'Hot Days 2024',
                'subtitle' => 'Clinical capacity for tomorrow.',
            ],
        ];
    }
}
