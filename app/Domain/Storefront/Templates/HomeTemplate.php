<?php

namespace App\Domain\Storefront\Templates;

use App\Domain\Storefront\StorefrontHomeTemplate;
use App\Models\StorefrontPageSection;

class HomeTemplate extends PageTemplate
{
    public function key(): string
    {
        return 'home';
    }

    public function label(): string
    {
        return 'Home';
    }

    public function description(): string
    {
        return 'Portada de la tienda con hero, especialidades, tarjetas y formulario.';
    }

    public function sections(): array
    {
        return StorefrontHomeTemplate::sections();
    }

    public function fixedTypes(): array
    {
        return [
            StorefrontPageSection::TYPE_HERO,
            StorefrontPageSection::TYPE_SPECIALTY_GRID,
            StorefrontPageSection::TYPE_FEATURE_CARDS,
            StorefrontPageSection::TYPE_BRAND_STRIP,
            StorefrontPageSection::TYPE_INQUIRY_FORM,
        ];
    }

    public function extraTypes(): array
    {
        return [
            StorefrontPageSection::TYPE_RECOMMENDED_PRODUCTS,
            StorefrontPageSection::TYPE_IMAGE_BANNER,
        ];
    }

    public function isSingleton(): bool
    {
        return true;
    }
}
