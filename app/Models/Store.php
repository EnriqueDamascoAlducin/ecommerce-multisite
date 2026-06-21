<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory, HasMedia;

    /** @var list<string> */
    protected $fillable = ['website_id', 'code', 'name', 'is_default', 'is_active', 'sort_order'];

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
     * @return BelongsTo<Website, $this>
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    /**
     * @return HasMany<StoreDomain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(StoreDomain::class);
    }

    /**
     * @return HasMany<StoreView, $this>
     */
    public function views(): HasMany
    {
        return $this->hasMany(StoreView::class);
    }

    /**
     * Usuarios administrativos con acceso a esta tienda.
     *
     * @return BelongsToMany<User, $this>
     */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'admin_user_store_permissions');
    }

    /** @return BelongsToMany<StorefrontPage, $this> */
    public function storefrontPages(): BelongsToMany
    {
        return $this->belongsToMany(StorefrontPage::class, 'storefront_page_store')
            ->withPivot([
                'meta_title',
                'meta_description',
                'meta_keywords',
                'robots_index',
                'robots_follow',
                'canonical_url',
                'og_title',
                'og_description',
                'og_media_id',
            ]);
    }

    /**
     * @param  Builder<Store>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
