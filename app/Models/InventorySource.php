<?php

namespace App\Models;

use Database\Factories\InventorySourceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventorySource extends Model
{
    /** @use HasFactory<InventorySourceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['code', 'name', 'is_default', 'is_active', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<InventoryStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    /**
     * @param  Builder<InventorySource>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first()
            ?? static::orderBy('sort_order')->first();
    }
}
