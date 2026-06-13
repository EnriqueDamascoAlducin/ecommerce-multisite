<?php

namespace App\Domain\Storefront\Templates;

use App\Models\StorefrontPageSection;

class AboutTemplate extends PageTemplate
{
    public function key(): string
    {
        return 'about';
    }

    public function label(): string
    {
        return 'Nosotros';
    }

    public function description(): string
    {
        return 'Presentación de la empresa con texto, imágenes y tarjetas.';
    }

    public function sections(): array
    {
        return [
            $this->pageHeader(0, 'Sobre nosotros', 'Quiénes somos'),
            $this->richText(1, '<p>Cuenta la historia y los valores de tu empresa.</p>'),
        ];
    }

    public function fixedTypes(): array
    {
        return [
            StorefrontPageSection::TYPE_PAGE_HEADER,
        ];
    }

    public function extraTypes(): array
    {
        return [
            StorefrontPageSection::TYPE_RICH_TEXT,
            StorefrontPageSection::TYPE_FEATURE_CARDS,
            StorefrontPageSection::TYPE_SPECIALTY_GRID,
            StorefrontPageSection::TYPE_IMAGE_BANNER,
        ];
    }
}
