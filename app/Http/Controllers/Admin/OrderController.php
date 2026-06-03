<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Inventory\StockReservationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentTransaction;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly StockReservationService $reservations,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $orders = Order::query()
            ->with(['store:id,name', 'website:id,name'])
            ->when($filters['search'], fn ($q, $search) => $q->where(
                fn ($w) => $w->where('number', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"),
            ))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'email' => $order->email,
                'total' => (string) $order->total,
                'store' => $order->store->name,
                'placed_at' => $order->placed_at?->toDateTimeString(),
            ]);

        return Inertia::render('admin/orders/index', [
            'orders' => $orders,
            'filters' => $filters,
            'statuses' => Order::STATUSES,
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load(['items', 'shippingAddress', 'billingAddress', 'customer:id,name,email', 'store:id,name', 'statusHistories.user:id,name', 'transactions']);

        $canInvoice = in_array($order->status, [Order::STATUS_PAID, Order::STATUS_PROCESSING], true)
            && ! $order->invoices()->exists();

        return Inertia::render('admin/orders/show', [
            'order' => [
                ...$order->only(['id', 'number', 'status', 'email', 'currency', 'subtotal', 'discount', 'shipping_amount', 'tax', 'total', 'shipping_method_label', 'payment_method']),
                'store' => $order->store->name,
                'placed_at' => $order->placed_at?->toDateTimeString(),
                'is_cancellable' => $order->isCancellable(),
                'can_invoice' => $canInvoice,
                'customer' => $order->customer?->only(['name', 'email']),
                'items' => $order->items->map(fn ($item) => [
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'line_total' => (string) $item->line_total,
                ])->values(),
                'shipping_address' => $order->shippingAddress,
                'billing_address' => $order->billingAddress,
                'history' => $order->statusHistories->map(fn (OrderStatusHistory $h) => [
                    'from_status' => $h->from_status,
                    'to_status' => $h->to_status,
                    'comment' => $h->comment,
                    'user' => $h->user?->name,
                    'created_at' => $h->created_at?->toDateTimeString(),
                ])->values(),
                'transactions' => $order->transactions->map(fn (PaymentTransaction $t) => [
                    'gateway' => $t->gateway,
                    'status' => $t->status,
                    'amount' => (string) $t->amount,
                    'currency' => $t->currency,
                    'gateway_transaction_id' => $t->gateway_transaction_id,
                    'created_at' => $t->created_at?->toDateTimeString(),
                ])->values(),
            ],
            'statuses' => Order::STATUSES,
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $data = $request->validated();

        $order->transitionTo($data['status'], $data['comment'] ?? null, $request->user()->id, ! empty($data['notify']));

        $this->auditLogger->log('order.status', $order, "Orden {$order->number}: {$data['status']}");

        return back()->with('success', 'Estado actualizado.');
    }

    public function addComment(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
            'notify' => ['boolean'],
        ]);

        $order->statusHistories()->create([
            'from_status' => $order->status,
            'to_status' => $order->status,
            'comment' => $data['comment'],
            'is_customer_notified' => ! empty($data['notify']),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Comentario agregado.');
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        if (! $order->isCancellable()) {
            return back()->with('error', 'Esta orden ya no puede cancelarse.');
        }

        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);

        // Libera el stock reservado de la orden y registra el cambio.
        $this->reservations->releaseByReference("order:{$order->id}");
        $order->transitionTo(Order::STATUS_CANCELLED, $data['comment'] ?? 'Orden cancelada.', $request->user()->id);

        $this->auditLogger->log('order.cancelled', $order, "Orden {$order->number} cancelada");

        return back()->with('success', 'Orden cancelada y stock liberado.');
    }
}
