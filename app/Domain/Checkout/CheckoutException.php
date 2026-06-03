<?php

namespace App\Domain\Checkout;

use RuntimeException;

class CheckoutException extends RuntimeException
{
    public static function emptyCart(): self
    {
        return new self('Tu carrito está vacío.');
    }

    public static function stockChanged(string $sku): self
    {
        return new self("El producto {$sku} ya no tiene stock suficiente.");
    }

    public static function notPurchasable(string $name): self
    {
        return new self("El producto «{$name}» ya no está disponible.");
    }
}
