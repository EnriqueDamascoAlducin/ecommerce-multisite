<?php

namespace App\Domain\Storefront;

use App\Models\StorefrontPageSection;

class StorefrontHomeTemplate
{
    /**
     * @return list<array{type: string, settings: array<string, mixed>}>
     */
    public static function sections(): array
    {
        return [
            self::hero(),
            self::specialtyGrid(),
            self::featureCards(),
            self::brandStrip(),
            self::inquiryForm(),
        ];
    }

    private static function hero(): array
    {
        return [
            'type' => StorefrontPageSection::TYPE_HERO,
            'settings' => [
                'background_color' => '#111827',
                'content_width' => 'container',
                'display_order' => 0,
                'eyebrow' => 'Campaign Launch',
                'title' => 'HOT DAYS 2024',
                'subtitle' => 'Maximize your clinical capacity with exclusive pricing on top-tier medical equipment. Professional grade technology for the specialists of tomorrow.',
                'media_id' => null,
                'buttons' => [
                    ['label' => 'Explore Offers', 'url' => '#'],
                    ['label' => 'View Catalog', 'url' => '#'],
                ],
            ],
        ];
    }

    private static function specialtyGrid(): array
    {
        return [
            'type' => StorefrontPageSection::TYPE_SPECIALTY_GRID,
            'settings' => [
                'background_color' => '#ffffff',
                'content_width' => 'container',
                'display_order' => 1,
                'eyebrow' => 'Medical Specialties',
                'title' => 'Medical Specialties',
                'items' => [
                    [
                        'title' => 'Cinesiterapia',
                        'text' => 'Advanced movement therapy and rehabilitation equipment for clinical excellence.',
                        'icon' => 'activity',
                        'link' => '#',
                        'highlighted' => false,
                        'wide' => true,
                        'media_id' => null,
                    ],
                    [
                        'title' => 'Electroterapia',
                        'text' => 'Precision stimulators.',
                        'icon' => 'zap',
                        'link' => '#',
                        'highlighted' => false,
                        'wide' => false,
                        'media_id' => null,
                    ],
                    [
                        'title' => 'Termoterapia',
                        'text' => 'Controlled thermal solutions.',
                        'icon' => 'activity',
                        'link' => '#',
                        'highlighted' => true,
                        'wide' => false,
                        'media_id' => null,
                    ],
                    [
                        'title' => 'Laserterapia',
                        'text' => '',
                        'icon' => 'sparkles',
                        'link' => '#',
                        'highlighted' => false,
                        'wide' => false,
                        'media_id' => null,
                    ],
                    [
                        'title' => 'Hidroterapia',
                        'text' => 'Specialized aquatic rehabilitation systems.',
                        'icon' => 'waves',
                        'link' => '#',
                        'highlighted' => false,
                        'wide' => true,
                        'media_id' => null,
                    ],
                    [
                        'title' => 'Ultrasonido',
                        'text' => '',
                        'icon' => 'activity',
                        'link' => '#',
                        'highlighted' => false,
                        'wide' => false,
                        'media_id' => null,
                    ],
                ],
            ],
        ];
    }

    private static function featureCards(): array
    {
        return [
            'type' => StorefrontPageSection::TYPE_FEATURE_CARDS,
            'settings' => [
                'background_color' => '#f5f5f5',
                'content_width' => 'container',
                'display_order' => 2,
                'items' => [
                    [
                        'title' => 'Technical Service',
                        'text' => 'Professional maintenance and repair by certified engineers. We ensure your clinical operations never stop with 24/7 priority support and genuine parts.',
                        'cta_label' => 'Schedule Service',
                        'cta_url' => '#',
                        'media_id' => null,
                    ],
                    [
                        'title' => 'Clinical Education',
                        'text' => 'Master the latest therapeutic techniques with our certified courses. Taught by industry leaders to help you get the most out of your high-performance equipment.',
                        'cta_label' => 'View Course Calendar',
                        'cta_url' => '#',
                        'media_id' => null,
                    ],
                ],
            ],
        ];
    }

    private static function brandStrip(): array
    {
        return [
            'type' => StorefrontPageSection::TYPE_BRAND_STRIP,
            'settings' => [
                'background_color' => '#ffffff',
                'content_width' => 'container',
                'display_order' => 3,
                'eyebrow' => 'Trusted Partners',
                'title' => 'Our Premier Brands',
                'brands' => [
                    'CHATTANOOGA',
                    'BTL MEDICAL',
                    'DJO GLOBAL',
                    'GYMNA',
                    'STIEGELMEYER',
                ],
            ],
        ];
    }

    private static function inquiryForm(): array
    {
        return [
            'type' => StorefrontPageSection::TYPE_INQUIRY_FORM,
            'settings' => [
                'background_color' => '#ffffff',
                'content_width' => 'container',
                'display_order' => 4,
                'title' => 'Elevate Your Practice Today',
                'text' => 'Our specialists are ready to provide a customized solution for your clinic\'s specific needs. Reach out for a consultation or a detailed quote.',
                'phone' => '+52 (55) 1234 5678',
                'email' => 'ventas@interferenciales.com.mx',
                'interest_areas' => [
                    'Electrotherapy Equipment',
                    'Technical Maintenance',
                    'Certification Courses',
                    'Full Clinic Setup',
                ],
                'media_id' => null,
            ],
        ];
    }
}
