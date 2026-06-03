<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStock extends Model
{
    /** @use HasFactory<\Database\Factories\InventoryStockFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'product_id', 'inventory_source_id', 'physical_qty', 'reserved_qty',
        'manage_stock', 'allow_backorders', 'low_stock_threshold',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'physical_qty' => 'integer',
            'reserved_qty' => 'integer',
            'manage_stock' => 'boolean',
            'allow_backorders' => 'boolean',
            'low_stock_threshold' => 'integer',
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
     * @return BelongsTo<InventorySource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(InventorySource::class, 'inventory_source_id');
    }

    /**
     * Stock disponible = físico − reservado.
     *
     * @return Attribute<int, never>
     */
    protected function availableQty(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->physical_qty - $this->reserved_qty,
        );
    }

    public function isLowStock(): bool
    {
        return $this->low_stock_threshold !== null
            && $this->available_qty <= $this->low_stock_threshold;
    }
}
