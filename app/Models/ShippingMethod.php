<?php

namespace App\Models;

use Database\Factories\ShippingMethodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingMethod extends Model
{
    /** @use HasFactory<ShippingMethodFactory> */
    use HasFactory;

    public const TYPE_FLAT_RATE = 'flat_rate';

    public const TYPE_FREE_SHIPPING = 'free_shipping';

    public const TYPE_PICKUP = 'pickup';

    /** @var list<string> */
    public const TYPES = [self::TYPE_FLAT_RATE, self::TYPE_FREE_SHIPPING, self::TYPE_PICKUP];

    /** @var list<string> */
    protected $fillable = ['code', 'name', 'type', 'is_active', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<StoreShippingMethod, $this>
     */
    public function storeMethods(): HasMany
    {
        return $this->hasMany(StoreShippingMethod::class);
    }

    /**
     * @param  Builder<ShippingMethod>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
