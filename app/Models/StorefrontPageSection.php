<?php

namespace App\Models;

use Database\Factories\StorefrontPageSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontPageSection extends Model
{
    /** @use HasFactory<StorefrontPageSectionFactory> */
    use HasFactory;

    public const TYPE_HERO = 'hero';

    public const TYPE_SPECIALTY_GRID = 'specialty_grid';

    public const TYPE_FEATURE_CARDS = 'feature_cards';

    public const TYPE_BRAND_STRIP = 'brand_strip';

    public const TYPE_INQUIRY_FORM = 'inquiry_form';

    public const TYPE_TEXT_IMAGE = 'text_image';

    public const TYPE_GALLERY = 'gallery';

    public const TYPE_CTA_BANNER = 'cta_banner';

    public const TYPE_FEATURED_PRODUCTS = 'featured_products';

    public const TYPES = [
        self::TYPE_HERO,
        self::TYPE_SPECIALTY_GRID,
        self::TYPE_FEATURE_CARDS,
        self::TYPE_BRAND_STRIP,
        self::TYPE_INQUIRY_FORM,
        self::TYPE_TEXT_IMAGE,
        self::TYPE_GALLERY,
        self::TYPE_CTA_BANNER,
        self::TYPE_FEATURED_PRODUCTS,
    ];

    /** @var list<string> */
    protected $fillable = ['storefront_page_id', 'type', 'sort_order', 'is_active', 'settings'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'settings' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(StorefrontPage::class, 'storefront_page_id');
    }
}
