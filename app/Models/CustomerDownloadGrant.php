<?php

namespace App\Models;

use Database\Factories\CustomerDownloadGrantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDownloadGrant extends Model
{
    /** @use HasFactory<CustomerDownloadGrantFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'order_id', 'order_item_id', 'customer_id', 'downloadable_link_id', 'product_id',
        'title', 'max_downloads', 'downloads_used', 'granted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_downloads' => 'integer',
            'downloads_used' => 'integer',
            'granted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<DownloadableLink, $this>
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(DownloadableLink::class, 'downloadable_link_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * ¿Quedan descargas disponibles? (null en max = ilimitado).
     */
    public function hasRemaining(): bool
    {
        return $this->max_downloads === null || $this->downloads_used < $this->max_downloads;
    }

    /**
     * Descargas restantes, o null si es ilimitado.
     */
    public function remaining(): ?int
    {
        if ($this->max_downloads === null) {
            return null;
        }

        return max(0, $this->max_downloads - $this->downloads_used);
    }
}
