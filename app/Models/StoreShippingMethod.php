<?php

namespace App\Models;

use Database\Factories\StoreShippingMethodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreShippingMethod extends Model
{
    /** @use HasFactory<StoreShippingMethodFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'store_id', 'shipping_method_id', 'label', 'is_active', 'sort_order',
        'free_over', 'min_subtotal', 'max_subtotal', 'countries',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'free_over' => 'decimal:2',
            'min_subtotal' => 'decimal:2',
            'max_subtotal' => 'decimal:2',
            'countries' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<ShippingMethod, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }

    /**
     * @return HasMany<ShippingRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class)->orderBy('sort_order');
    }

    public function displayLabel(): string
    {
        return $this->label ?: $this->method?->name ?? '';
    }

    /**
     * @param  Builder<StoreShippingMethod>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
