<?php

namespace App\Domain\Sales;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class GenerateInvoiceAction
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
    ) {}

    public function execute(Order $order): Invoice
    {
        $order->loadMissing('items', 'store.website');

        return DB::transaction(function () use ($order) {
            $order->transitionTo(Order::STATUS_INVOICED, 'Factura generada.');

            $number = $this->numbers->next($order->website);

            $invoice = Invoice::create([
                'website_id' => $order->website_id,
                'store_id' => $order->store_id,
                'order_id' => $order->id,
                'number' => $number,
                'currency' => $order->currency,
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'shipping_amount' => $order->shipping_amount,
                'tax' => $order->tax,
                'total' => $order->total,
                'invoiced_at' => now(),
            ]);

            foreach ($order->items as $item) {
                $invoice->items()->create([
                    'order_item_id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ]);
            }

            return $invoice;
        });
    }
}
