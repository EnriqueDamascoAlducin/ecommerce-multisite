<?php

namespace App\Models;

use Database\Factories\CatalogPriceRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogPriceRule extends Model
{
    /** @use HasFactory<CatalogPriceRuleFactory> */
    use HasFactory;

    public const ACTION_PERCENT = 'percent';

    public const ACTION_FIXED_AMOUNT = 'fixed_amount';

    public const ACTION_FIXED_PRICE = 'fixed_price';

    /** @var list<string> */
    public const ACTIONS = [self::ACTION_PERCENT, self::ACTION_FIXED_AMOUNT, self::ACTION_FIXED_PRICE];

    /** @var list<string> */
    protected $fillable = [
        'website_id', 'category_id', 'name', 'description', 'action', 'value',
        'priority', 'starts_at', 'ends_at', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Website, $this>
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param  Builder<CatalogPriceRule>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function isWithinWindow(): bool
    {
        $now = now();

        if ($this->starts_at && $now->lessThan($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->greaterThan($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function matchesWebsite(?int $websiteId): bool
    {
        return $this->website_id === null || $this->website_id === $websiteId;
    }

    /**
     * Aplica la acción de la regla a un precio, sin bajar de 0.
     */
    public function applyTo(float $price): float
    {
        $result = match ($this->action) {
            self::ACTION_PERCENT => $price * (1 - ((float) $this->value / 100)),
            self::ACTION_FIXED_AMOUNT => $price - (float) $this->value,
            self::ACTION_FIXED_PRICE => (float) $this->value,
            default => $price,
        };

        return max(0.0, round($result, 2));
    }
}
