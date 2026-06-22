<?php

namespace App\Domain\Checkout;

use App\Domain\Catalog\ProductPurchasabilityService;
use App\Domain\Inventory\StockAvailabilityChecker;
use App\Models\Cart;

/**
 * Validación final antes de crear la orden: cada ítem sigue siendo comprable
 * (activo + habilitado en la tienda) y con stock suficiente.
 */
class TotalsValidator
{
    public function __construct(
        private readonly StockAvailabilityChecker $availability,
        private readonly ProductPurchasabilityService $purchasability,
    ) {}

    public function validate(Cart $cart): void
    {
        $cart->loadMissing('items.product.inventoryStocks', 'items.product.storeLinks');

        foreach ($cart->items as $item) {
            $product = $item->product;

            if (! $product || ! $this->purchasability->isPurchasable($product, $cart->store_id)) {
                throw CheckoutException::notPurchasable($item->name);
            }

            if (! $this->availability->canFulfill($product, $item->quantity)) {
                throw CheckoutException::stockChanged($item->sku);
            }
        }
    }
}
