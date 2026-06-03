<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasMedia;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const TYPE_SIMPLE = 'simple';

    /** @var list<string> */
    protected $fillable = [
        'type', 'sku', 'name', 'slug', 'short_description', 'description',
        'status', 'visibility', 'weight', 'attributes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
            'attributes' => 'array',
        ];
    }

    /**
     * @return HasMany<ProductPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * @return HasMany<ProductStore, $this>
     */
    public function storeLinks(): HasMany
    {
        return $this->hasMany(ProductStore::class);
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * @return HasMany<ProductAttributeValue, $this>
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    /**
     * @return HasMany<InventoryStock, $this>
     */
    public function inventoryStocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    /**
     * Disponible total sumando todas las fuentes (físico − reservado).
     */
    public function totalAvailableQty(): int
    {
        return $this->inventoryStocks->sum(fn (InventoryStock $stock) => $stock->available_qty);
    }

    public function basePrice(): ?ProductPrice
    {
        return $this->prices->firstWhere('store_id', null);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Está habilitado en la tienda dada (fila product_stores activa).
     */
    public function isActiveInStore(int $storeId): bool
    {
        return $this->storeLinks->firstWhere('store_id', $storeId)?->is_active ?? false;
    }
}
