<?php

namespace App\Models;

use Database\Factories\HeaderMenuItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeaderMenuItem extends Model
{
    /** @use HasFactory<HeaderMenuItemFactory> */
    use HasFactory;

    protected $table = 'store_header_menu_items';

    public const TYPE_ALL_CATEGORIES = 'all_categories';

    public const TYPE_LINK = 'link';

    public const TYPE_CATEGORY = 'category';

    public const TYPE_PRODUCT = 'product';

    public const TYPE_CUSTOM = 'custom';

    /** @var list<string> */
    protected $fillable = [
        'store_id', 'parent_id', 'type', 'label', 'url',
        'category_id', 'product_id', 'is_active', 'expand_products', 'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expand_products' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order');
    }
}
