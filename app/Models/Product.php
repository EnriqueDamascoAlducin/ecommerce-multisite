<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasMedia;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const TYPE_SIMPLE = 'simple';

    public const TYPE_CONFIGURABLE = 'configurable';

    public const TYPE_BUNDLE = 'bundle';

    public const PRICE_TYPE_DYNAMIC = 'dynamic';

    public const PRICE_TYPE_FIXED = 'fixed';

    /** @var list<string> */
    protected $fillable = [
        'type', 'price_type', 'parent_id', 'sku', 'name', 'slug', 'short_description', 'description',
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
     * @return BelongsTo<Product, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsToMany<Attribute, $this>
     */
    public function configurableAttributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_configurable_attributes');
    }

    /**
     * Componentes cuando el producto es un bundle.
     *
     * @return HasMany<BundleItem, $this>
     */
    public function bundleItems(): HasMany
    {
        return $this->hasMany(BundleItem::class, 'bundle_product_id')->orderBy('sort_order');
    }

    /**
     * Variantes hijas activas (productos simples con parent_id).
     *
     * @return HasMany<Product, $this>
     */
    public function variants(): HasMany
    {
        return $this->children()->where('type', self::TYPE_SIMPLE);
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
     * Precio más bajo entre las variantes (para el padre configurable).
     */
    public function lowestVariantPrice(int $storeId): ?ProductPrice
    {
        $variants = $this->variants()->with(['prices' => fn ($q) => $q->where('store_id', $storeId)])->get();

        $min = $variants->flatMap->prices->sortBy('price')->first();

        return $min ?? $this->basePrice();
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

    /**
     * ¿Este producto es configurable y tiene variantes?
     */
    public function isConfigurable(): bool
    {
        return $this->type === self::TYPE_CONFIGURABLE;
    }

    /**
     * ¿Este producto es un paquete (bundle) compuesto por otros productos?
     */
    public function isBundle(): bool
    {
        return $this->type === self::TYPE_BUNDLE;
    }

    /**
     * ¿El precio del bundle es la suma dinámica de sus componentes?
     */
    public function hasDynamicBundlePrice(): bool
    {
        return $this->price_type !== self::PRICE_TYPE_FIXED;
    }
}
