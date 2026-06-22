<?php

namespace App\Http\Controllers\Storefront\Account;

use App\Domain\Store\StoreContext;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(private readonly StoreContext $context) {}

    public function index(Request $request): Response
    {
        $orders = $request->user('customer')->orders()
            ->where('store_id', $this->context->store()->id)
            ->with('items:id,order_id,name,sku,quantity,line_total')
            ->latest('placed_at')
            ->paginate(10)
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'total' => (string) $order->total,
                'placed_at' => $order->placed_at?->toIso8601String(),
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'line_total' => (string) $item->line_total,
                ])->values(),
            ]);

        return Inertia::render('storefront/account/orders', [
            'orders' => $orders,
        ]);
    }
}
