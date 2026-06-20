<?php

namespace Database\Factories;

use App\Models\Website;
use App\Models\WebsiteHeaderSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteHeaderSettings>
 */
class WebsiteHeaderSettingsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'cintillo_enabled' => true,
            'cintillo_show_on_mobile' => true,
            'cintillo_blocks' => [
                ['type' => 'text', 'text' => 'Envío gratis en compras mayores a $999'],
            ],
            'cintillo_text_color' => '#ffffff',
            'cintillo_background_color' => '#111827',
            'footer_settings' => [
                'enabled' => true,
                'description' => 'Tienda en línea con atención personalizada.',
                'copyright' => '© {year} Mi tienda. Todos los derechos reservados.',
                'background_color' => null,
                'text_color' => null,
                'columns' => [],
                'contact' => [],
                'social' => [],
            ],
        ];
    }

    public function social(): static
    {
        return $this->state(fn () => [
            'cintillo_blocks' => [
                ['type' => 'social', 'social' => [
                    ['platform' => 'facebook', 'url' => 'https://facebook.com/mitienda'],
                    ['platform' => 'instagram', 'url' => 'https://instagram.com/mitienda'],
                ]],
            ],
        ]);
    }

    public function mixed(): static
    {
        return $this->state(fn () => [
            'cintillo_blocks' => [
                ['type' => 'text', 'text' => 'Tel: 555-1234'],
                ['type' => 'social', 'social' => [
                    ['platform' => 'facebook', 'url' => 'https://facebook.com/mitienda'],
                ]],
            ],
        ]);
    }

    public function image(): static
    {
        return $this->state(fn () => [
            'cintillo_blocks' => [
                ['type' => 'image', 'images' => [
                    ['url' => 'https://cdn.example.com/promo.png', 'alt' => 'Promo', 'link' => 'https://example.com'],
                    ['url' => 'https://cdn.example.com/veterinaria.png', 'alt' => 'Veterinaria', 'link' => 'https://veterinaria.example.com'],
                ]],
            ],
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['cintillo_enabled' => false]);
    }
}
