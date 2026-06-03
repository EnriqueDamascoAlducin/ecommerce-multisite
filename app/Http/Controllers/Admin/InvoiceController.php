<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Sales\GenerateInvoiceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly GenerateInvoiceAction $generateInvoice,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $invoices = Invoice::query()
            ->with(['store:id,name', 'website:id,name', 'order:id,number'])
            ->when($filters['search'], fn ($q, $search) => $q->where(
                fn ($w) => $w->where('number', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($o) => $o->where('number', 'like', "%{$search}%")),
            ))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'order_number' => $invoice->order->number,
                'order_id' => $invoice->order_id,
                'status' => $invoice->status,
                'total' => (string) $invoice->total,
                'store' => $invoice->store->name,
                'invoiced_at' => $invoice->invoiced_at?->toDateTimeString(),
            ]);

        return Inertia::render('admin/invoices/index', [
            'invoices' => $invoices,
            'filters' => $filters,
            'statuses' => Invoice::STATUSES,
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load(['items', 'order:id,number,status', 'store:id,name', 'website:id,name']);

        return Inertia::render('admin/invoices/show', [
            'invoice' => [
                ...$invoice->only(['id', 'number', 'status', 'currency', 'subtotal', 'discount', 'shipping_amount', 'tax', 'total']),
                'store' => $invoice->store->name,
                'website' => $invoice->website->name,
                'order_number' => $invoice->order->number,
                'order_id' => $invoice->order_id,
                'order_status' => $invoice->order->status,
                'invoiced_at' => $invoice->invoiced_at?->toDateTimeString(),
                'items' => $invoice->items->map(fn ($item) => [
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'line_total' => (string) $item->line_total,
                ])->values(),
            ],
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $order = Order::findOrFail($request->validated('order_id'));

        if ($order->invoices()->exists()) {
            return back()->with('error', 'Esta orden ya tiene una factura.');
        }

        if (! in_array($order->status, [Order::STATUS_PAID, Order::STATUS_PROCESSING], true)) {
            return back()->with('error', 'Solo se pueden facturar órdenes pagadas o en proceso.');
        }

        $this->generateInvoice->execute($order);

        return to_route('admin.orders.show', $order)->with('success', 'Factura generada.');
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status !== Invoice::STATUS_PENDING) {
            return back()->with('error', 'Solo se pueden cancelar facturas pendientes.');
        }

        $order = $invoice->order;

        $invoice->update(['status' => Invoice::STATUS_CANCELLED]);

        $order->transitionTo(Order::STATUS_PAID, "Factura {$invoice->number} cancelada.");

        return back()->with('success', 'Factura cancelada.');
    }
}
