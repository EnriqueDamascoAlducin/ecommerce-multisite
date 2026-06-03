<?php

namespace App\Domain\Payment;

/**
 * Resultado de iniciar un pago. Para pasarelas con checkout alojado trae una
 * `redirectUrl` (a dónde mandar al cliente); para pagos offline viene null.
 */
class PaymentResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $transactionId = null,
        public readonly ?string $reference = null,
        public readonly array $payload = [],
    ) {}

    public function requiresRedirect(): bool
    {
        return $this->redirectUrl !== null;
    }
}
