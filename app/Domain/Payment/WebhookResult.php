<?php

namespace App\Domain\Payment;

/**
 * Resultado normalizado de una notificación entrante de un gateway, ya mapeado
 * a la orden y al estado de pago correspondiente.
 */
class WebhookResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $eventId,
        public readonly int $orderId,
        public readonly PaymentStatus $status,
        public readonly ?string $transactionId = null,
        public readonly ?string $type = null,
        public readonly array $payload = [],
    ) {}
}
