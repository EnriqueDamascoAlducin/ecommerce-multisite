<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAddress extends Model
{
    public const TYPE_SHIPPING = 'shipping';

    public const TYPE_BILLING = 'billing';

    /** @var list<string> */
    protected $fillable = [
        'order_id', 'type', 'first_name', 'last_name', 'company', 'phone',
        'line1', 'line2', 'neighborhood', 'city', 'state', 'postal_code', 'country',
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
