<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use Database\Factories\WebsiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function defaultStore(): ?Store
    {
        return $this->stores()->where('is_default', true)->first()
            ?? $this->stores()->orderBy('sort_order')->first();
    }
}
