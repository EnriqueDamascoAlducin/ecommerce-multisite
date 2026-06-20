<?php

namespace App\Models;

use Database\Factories\StorefrontPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorefrontPage extends Model
{
    /** @use HasFactory<StorefrontPageFactory> */
    use HasFactory;

    public const HOME = 'home';

    /** @var list<string> */
    protected $fillable = ['store_id', 'slug', 'template', 'title', 'is_published'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsToMany<Store, $this> */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'storefront_page_store');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(StorefrontPageSection::class)->orderBy('id');
    }
}
