<?php

namespace App\Models;

use Database\Factories\CartPriceRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartPriceRule extends Model
{
    /** @use HasFactory<CartPriceRuleFactory> */
    use HasFactory;

    public const ACTION_PERCENT = 'percent';

    public const ACTION_FIXED = 'fixed';

    public const ACTION_FREE_SHIPPING = 'free_shipping';

    /** @var list<string> */
    public const ACTIONS = [self::ACTION_PERCENT, self::ACTION_FIXED, self::ACTION_FREE_SHIPPING];

    /** @var list<string> */
    protected $fillable = [
        'website_id', 'name', 'description', 'coupon_code', 'action', 'value',
        'min_subtotal', 'starts_at', 'ends_at', 'is_active', 'usage_limit', 'times_used',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_subtotal' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'usage_limit' => 'integer',
            'times_used' => 'integer',
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
     * @param  Builder<CartPriceRule>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function requiresCoupon(): bool
    {
        return $this->coupon_code !== null && $this->coupon_code !== '';
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

    public function hasUsesLeft(): bool
    {
        return $this->usage_limit === null || $this->times_used < $this->usage_limit;
    }

    public function meetsMinimum(float $subtotal): bool
    {
        return $this->min_subtotal === null || $subtotal >= (float) $this->min_subtotal;
    }

    public function grantsFreeShipping(): bool
    {
        return $this->action === self::ACTION_FREE_SHIPPING;
    }

    /**
     * Descuento monetario que aplica esta regla a un subtotal dado.
     */
    public function discountAmount(float $subtotal): float
    {
        return match ($this->action) {
            self::ACTION_PERCENT => round($subtotal * ((float) $this->value / 100), 2),
            self::ACTION_FIXED => min((float) $this->value, $subtotal),
            default => 0.0,
        };
    }

    /**
     * ¿La regla está utilizable ahora mismo (activa, en ventana, con usos)?
     */
    public function isUsable(): bool
    {
        return $this->is_active && $this->isWithinWindow() && $this->hasUsesLeft();
    }
}
