<?php

namespace App\Domain\Storefront\Templates;

use App\Models\StorefrontPageSection;

abstract class PageTemplate
{
    /**
     * Stable identifier stored on storefront_pages.template.
     */
    abstract public function key(): string;

    /**
     * Human label shown in the admin template picker.
     */
    abstract public function label(): string;

    /**
     * Short description shown in the admin template picker.
     */
    abstract public function description(): string;

    /**
     * Sections seeded when a page is created (or re-seeded when missing).
     *
     * @return list<array{type: string, settings: array<string, mixed>}>
     */
    abstract public function sections(): array;

    /**
     * Structural section types: exactly one per page, cannot be added or
     * removed by the admin (only their content is editable).
     *
     * @return list<string>
     */
    public function fixedTypes(): array
    {
        return [];
    }

    /**
     * Section types the admin may freely add, remove and reorder.
     *
     * @return list<string>
     */
    public function extraTypes(): array
    {
        return [];
    }

    /**
     * Singleton templates (the home page) are created automatically and are
     * never offered in the "create page" picker.
     */
    public function isSingleton(): bool
    {
        return false;
    }

    /**
     * Every section type allowed on a page using this template.
     *
     * @return list<string>
     */
    public function allowedTypes(): array
    {
        return array_values(array_unique([...$this->fixedTypes(), ...$this->extraTypes()]));
    }

    /**
     * @return array{type: string, settings: array<string, mixed>}
     */
    protected function pageHeader(int $order, string $title, string $eyebrow = '', string $subtitle = ''): array
    {
        return [
            'type' => StorefrontPageSection::TYPE_PAGE_HEADER,
            'settings' => [
                'background_color' => '#0f172a',
                'content_width' => 'container',
                'display_order' => $order,
                'eyebrow' => $eyebrow,
                'title' => $title,
                'subtitle' => $subtitle,
            ],
        ];
    }

    /**
     * @return array{type: string, settings: array<string, mixed>}
     */
    protected function richText(int $order, string $html = '<p>Escribe aquí el contenido de la página.</p>'): array
    {
        return [
            'type' => StorefrontPageSection::TYPE_RICH_TEXT,
            'settings' => [
                'background_color' => '#ffffff',
                'content_width' => 'container',
                'display_order' => $order,
                'html' => $html,
            ],
        ];
    }

    /**
     * @return array{type: string, settings: array<string, mixed>}
     */
    protected function contactInfo(int $order): array
    {
        return [
            'type' => StorefrontPageSection::TYPE_CONTACT_INFO,
            'settings' => [
                'background_color' => '#ffffff',
                'content_width' => 'container',
                'display_order' => $order,
                'title' => 'Información de contacto',
                'phone' => '',
                'email' => '',
                'address' => '',
                'hours' => '',
                'map_url' => '',
            ],
        ];
    }

    /**
     * @return array{type: string, settings: array<string, mixed>}
     */
    protected function inquiryForm(int $order, string $title = 'Escríbenos'): array
    {
        return [
            'type' => StorefrontPageSection::TYPE_INQUIRY_FORM,
            'settings' => [
                'background_color' => '#ffffff',
                'content_width' => 'container',
                'display_order' => $order,
                'title' => $title,
                'text' => 'Cuéntanos qué necesitas y nuestro equipo te contactará.',
                'phone' => '',
                'email' => '',
                'interest_areas' => [],
                'media_id' => null,
            ],
        ];
    }
}
