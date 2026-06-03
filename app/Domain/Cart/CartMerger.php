<?php

namespace App\Domain\Cart;

use App\Models\Cart;
use Illuminate\Support\Facades\DB;

/**
 * Fusiona el carrito de invitado en el carrito del cliente al iniciar sesión.
 * Las cantidades de productos repetidos se suman.
 */
class CartMerger
{
    public function merge(Cart $guest, Cart $target): Cart
    {
        if ($guest->is($target)) {
            return $target;
        }

        DB::transaction(function () use ($guest, $target) {
            $guest->loadMissing('items');

            foreach ($guest->items as $item) {
                $existing = $target->items()->where('product_id', $item->product_id)->first();

                if ($existing) {
                    $existing->increment('quantity', $item->quantity);
                } else {
                    $target->items()->create([
                        'product_id' => $item->product_id,
                        'sku' => $item->sku,
                        'name' => $item->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                    ]);
                }
            }

            $guest->delete();
        });

        return $target->load('items');
    }
}
