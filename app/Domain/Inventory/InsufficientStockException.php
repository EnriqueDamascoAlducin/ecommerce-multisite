<?php

namespace App\Domain\Inventory;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public static function for(string $sku, int $requested, int $available): self
    {
        return new self("Stock insuficiente para {$sku}: solicitado {$requested}, disponible {$available}.");
    }
}
