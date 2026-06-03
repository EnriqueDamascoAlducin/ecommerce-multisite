<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItemOption extends Model
{
    /** @var list<string> */
    protected $fillable = ['cart_item_id', 'code', 'label', 'value', 'price_delta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CartItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(CartItem::class, 'cart_item_id');
    }
}
