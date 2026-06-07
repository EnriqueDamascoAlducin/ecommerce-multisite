<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use Database\Factories\WebsiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Website extends Model
{
    /** @use HasFactory<WebsiteFactory> */
    use HasFactory, HasMedia;

    /** @var list<string> */
    protected $fillable = ['code', 'name', 'is_default', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Store, $this>
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /**
     * @return HasOne<WebsiteHeaderSettings, $this>
     */
    public function headerSettings(): HasOne
    {
        return $this->hasOne(WebsiteHeaderSettings::class);
    }

    /**
     * @return HasMany<CustomerGroup, $this>
     */
    public function customerGroups(): HasMany
    {
        return $this->hasMany(CustomerGroup::class);
    }

    public function defaultStore(): ?Store
    {
        return $this->stores()->where('is_default', true)->first()
            ?? $this->stores()->orderBy('sort_order')->first();
    }
}
