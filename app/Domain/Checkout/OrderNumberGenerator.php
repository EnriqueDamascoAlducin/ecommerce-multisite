<?php

namespace App\Domain\Checkout;

use App\Models\Order;
use App\Models\Website;

/**
 * Genera un número de orden incremental por sitio (website).
 * Pensado para ejecutarse dentro de la transacción de creación de la orden.
 */
class OrderNumberGenerator
{
    public function next(Website $website): string
    {
        $sequence = Order::where('website_id', $website->id)->count() + 1001;

        return strtoupper($website->code).'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
