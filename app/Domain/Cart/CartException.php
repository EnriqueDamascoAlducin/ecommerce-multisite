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
}
