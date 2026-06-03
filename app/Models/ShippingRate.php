<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    /** @var list<string> */
    protected $fillable = ['store_shipping_method_id', 'min_subtotal', 'max_subtotal', 'amount', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_subtotal' => 'decimal:2',
            'max_subtotal' => 'decimal:2',
            'amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<StoreShippingMethod, $this>
     */
    public function storeMethod(): BelongsTo
    {
        return $this->belongsTo(StoreShippingMethod::class, 'store_shipping_method_id');
    }

    /**
     * ¿El subtotal cae dentro de este tramo?
     */
    public function matches(float $subtotal): bool
    {
        return $subtotal >= (float) $this->min_subtotal
            && ($this->max_subtotal === null || $subtotal <= (float) $this->max_subtotal);
    }
}
