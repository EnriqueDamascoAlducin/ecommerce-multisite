<?php

namespace App\Domain\Storefront\Templates;

use App\Models\StorefrontPageSection;

class LegalTemplate extends PageTemplate
{
    public function key(): string
    {
        return 'legal';
    }

    public function label(): string
    {
        return 'Legal / Políticas';
    }

    public function description(): string
    {
        return 'Página de texto para privacidad, términos, devoluciones y similares.';
    }

    public function sections(): array
    {
        return [
            $this->pageHeader(0, 'Aviso legal'),
            $this->richText(1, '<p>Redacta aquí el contenido de tu política.</p>'),
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
        ];
    }
}
