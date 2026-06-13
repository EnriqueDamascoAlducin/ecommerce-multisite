<?php

namespace App\Domain\Storefront\Templates;

use App\Models\StorefrontPageSection;

class ContactTemplate extends PageTemplate
{
    public function key(): string
    {
        return 'contact';
    }

    public function label(): string
    {
        return 'Contacto';
    }

    public function description(): string
    {
        return 'Encabezado, datos de contacto y formulario de consulta.';
    }

    public function sections(): array
    {
        return [
            $this->pageHeader(0, 'Contáctanos', 'Estamos para ayudarte', 'Resuelve tus dudas o solicita una cotización.'),
            $this->contactInfo(1),
            $this->inquiryForm(2, 'Envíanos un mensaje'),
        ];
    }

    public function fixedTypes(): array
    {
        return [
            StorefrontPageSection::TYPE_PAGE_HEADER,
            StorefrontPageSection::TYPE_CONTACT_INFO,
            StorefrontPageSection::TYPE_INQUIRY_FORM,
        ];
    }

    public function extraTypes(): array
    {
        return [
            StorefrontPageSection::TYPE_RICH_TEXT,
            StorefrontPageSection::TYPE_IMAGE_BANNER,
        ];
    }
}
