<?php

namespace App\Domain\Catalog;

use App\Models\CustomerDownloadGrant;
use App\Models\Order;

/**
 * Otorga acceso de descarga al pagarse una orden con productos descargables.
 * Idempotente: una orden concede cada enlace una sola vez (índice único).
 */
class DownloadGrantService
{
    public function grantForOrder(Order $order): void
    {
        $order->loadMissing('items.product.downloadableLinks');

        foreach ($order->items as $item) {
            $product = $item->product;

            if (! $product || ! $product->isDownloadable()) {
                continue;
            }

            foreach ($product->downloadableLinks as $link) {
                CustomerDownloadGrant::firstOrCreate(
                    ['order_id' => $order->id, 'downloadable_link_id' => $link->id],
                    [
                        'order_item_id' => $item->id,
                        'customer_id' => $order->customer_id,
                        'product_id' => $product->id,
                        'title' => $link->title,
                        'max_downloads' => $link->max_downloads,
                        'downloads_used' => 0,
                        'granted_at' => now(),
                    ],
                );
            }
        }
    }
}
