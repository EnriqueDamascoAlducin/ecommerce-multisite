<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAYMENT_REVIEW = 'payment_review';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PAID = 'paid';

    public const STATUS_INVOICED = 'invoiced';

    public const STATUS_PARTIALLY_SHIPPED = 'partially_shipped';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_COMPLETE = 'complete';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PAYMENT_REVIEW,
        self::STATUS_PROCESSING,
        self::STATUS_PAID,
        self::STATUS_INVOICED,
        self::STATUS_PARTIALLY_SHIPPED,
        self::STATUS_SHIPPED,
        self::STATUS_COMPLETE,
        self::STATUS_CANCELLED,
        self::STATUS_FAILED,
        self::STATUS_REFUNDED,
    ];

    /** Estados finales en los que la orden ya no puede cancelarse. */
    public const NON_CANCELLABLE = [
        self::STATUS_COMPLETE,
        self::STATUS_CANCELLED,
        self::STATUS_REFUNDED,
        self::STATUS_SHIPPED,
    ];

    /** @var list<string> */
    protected $fillable = [
        'website_id', 'store_id', 'customer_id', 'number', 'status', 'email', 'currency',
        'subtotal', 'discount', 'shipping_amount', 'tax', 'total',
        'shipping_method_code', 'shipping_method_label', 'payment_method', 'placed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'placed_at' => 'datetime',
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
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<OrderAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    /**
     * @return HasOne<OrderAddress, $this>
     */
    public function shippingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'shipping');
    }

    /**
     * @return HasOne<OrderAddress, $this>
     */
    public function billingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'billing');
    }

    /**
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    /**
     * @return HasMany<PaymentTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->latest();
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function isCancellable(): bool
    {
        return ! in_array($this->status, self::NON_CANCELLABLE, true);
    }

    /**
     * Cambia el estado registrando el historial.
     */
    public function transitionTo(string $status, ?string $comment = null, ?int $userId = null, bool $notify = false): void
    {
        $from = $this->status;
        $this->update(['status' => $status]);

        $this->statusHistories()->create([
            'from_status' => $from,
            'to_status' => $status,
            'comment' => $comment,
            'is_customer_notified' => $notify,
            'user_id' => $userId,
        ]);
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function scopeForStore(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }
}
