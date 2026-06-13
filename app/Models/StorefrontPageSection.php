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

    public const TYPE_RECOMMENDED_PRODUCTS = 'recommended_products';

    public const TYPE_IMAGE_BANNER = 'image_banner';

    public const TYPE_PAGE_HEADER = 'page_header';

    public const TYPE_RICH_TEXT = 'rich_text';

    public const TYPE_CONTACT_INFO = 'contact_info';

    public const TYPES = [
        self::TYPE_HERO,
        self::TYPE_SPECIALTY_GRID,
        self::TYPE_FEATURE_CARDS,
        self::TYPE_BRAND_STRIP,
        self::TYPE_INQUIRY_FORM,
        self::TYPE_RECOMMENDED_PRODUCTS,
        self::TYPE_IMAGE_BANNER,
        self::TYPE_PAGE_HEADER,
        self::TYPE_RICH_TEXT,
        self::TYPE_CONTACT_INFO,
    ];

    public const FIXED_TYPES = [
        self::TYPE_HERO,
        self::TYPE_SPECIALTY_GRID,
        self::TYPE_FEATURE_CARDS,
        self::TYPE_BRAND_STRIP,
        self::TYPE_INQUIRY_FORM,
    ];

    public const EXTRA_TYPES = [
        self::TYPE_RECOMMENDED_PRODUCTS,
        self::TYPE_IMAGE_BANNER,
    ];

    /** @var list<string> */
    protected $fillable = ['storefront_page_id', 'type', 'settings'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(StorefrontPage::class, 'storefront_page_id');
    }
}
