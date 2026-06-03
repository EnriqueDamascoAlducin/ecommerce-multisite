<?php

namespace App\Domain\Payment;

use App\Models\Order;

/**
 * Estado normalizado de un pago, independiente del gateway. Cada pasarela mapea
 * sus estados propios a uno de estos valores.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * Estado de orden al que corresponde este resultado de pago.
     */
    public function orderStatus(): string
    {
        return match ($this) {
            self::Pending => Order::STATUS_PAYMENT_REVIEW,
            self::Paid => Order::STATUS_PAID,
            self::Failed => Order::STATUS_FAILED,
            self::Cancelled => Order::STATUS_CANCELLED,
            self::Refunded => Order::STATUS_REFUNDED,
        };
    }

    /**
     * Indica si este resultado debe liberar el stock reservado de la orden.
     */
    public function releasesStock(): bool
    {
        return in_array($this, [self::Cancelled, self::Refunded], true);
    }
}
