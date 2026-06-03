<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ProductPrice extends Model
{
    /** @use HasFactory<\Database\Factories\ProductPriceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'product_id', 'store_id', 'price', 'special_price', 'special_price_from', 'special_price_to',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'special_price' => 'decimal:2',
            'special_price_from' => 'date',
            'special_price_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isSpecialActive(?Carbon $now = null): bool
    {
        if ($this->special_price === null) {
            return false;
        }

        $now ??= Carbon::now();

        if ($this->special_price_from && $now->lt($this->special_price_from->startOfDay())) {
            return false;
        }

        if ($this->special_price_to && $now->gt($this->special_price_to->endOfDay())) {
            return false;
        }

        return true;
    }

    public function effectivePrice(?Carbon $now = null): string
    {
        return $this->isSpecialActive($now) ? (string) $this->special_price : (string) $this->price;
    }
}
