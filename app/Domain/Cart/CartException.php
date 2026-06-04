<?php

namespace App\Domain\Cart;

use RuntimeException;

class CartException extends RuntimeException
{
    public static function notPurchasable(string $name): self
    {
        return new self("El producto «{$name}» no está disponible en esta tienda.");
    }

    public static function shippingUnavailable(): self
    {
        return new self('El método de envío seleccionado no está disponible.');
    }

    public static function invalidCoupon(): self
    {
        return new self('El cupón no es válido o ya expiró.');
    }

    public static function couponMinimumNotMet(string $minimum): self
    {
        return new self("Este cupón requiere un subtotal mínimo de \${$minimum}.");
    }
}
