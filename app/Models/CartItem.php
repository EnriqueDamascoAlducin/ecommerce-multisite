<?php

namespace App\Models;

use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'cart_id', 'product_id', 'sku', 'name', 'quantity', 'unit_price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<CartItemOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(CartItemOption::class);
    }

    /**
     * Subtotal de la línea (precio unitario × cantidad).
     *
     * @return Attribute<string, never>
     */
    protected function lineTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format((float) $this->unit_price * $this->quantity, 2, '.', ''),
        );
    }
}
