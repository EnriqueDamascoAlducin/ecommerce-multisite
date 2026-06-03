<?php

namespace App\Domain\Payment;

use RuntimeException;

class PaymentException extends RuntimeException
{
    public static function unknownGateway(string $code): self
    {
        return new self("No existe una pasarela de pago con el código «{$code}».");
    }

    public static function gatewayUnavailable(string $code): self
    {
        return new self("La pasarela de pago «{$code}» no está configurada.");
    }

    public static function startFailed(string $code, string $reason = ''): self
    {
        return new self(trim("No se pudo iniciar el pago con «{$code}». {$reason}"));
    }
}
