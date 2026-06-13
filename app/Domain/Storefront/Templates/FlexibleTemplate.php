<?php

namespace App\Domain\Storefront\Templates;

use App\Models\StorefrontPageSection;

class FlexibleTemplate extends PageTemplate
{
    public function key(): string
    {
        return 'flexible';
    }

    public function label(): string
    {
        return 'Flexible / En blanco';
    }

    public function description(): string
    {
        return 'Página vacía: agrega libremente cualquier sección disponible.';
    }

    public function sections(): array
    {
        return [];
    }

    public function extraTypes(): array
    {
        return StorefrontPageSection::TYPES;
    }
}
