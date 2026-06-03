<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerAddressFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'customer_id', 'label', 'first_name', 'last_name', 'company', 'phone',
        'line1', 'line2', 'city', 'state', 'postal_code', 'country',
        'is_default_shipping', 'is_default_billing',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default_shipping' => 'boolean',
            'is_default_billing' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
