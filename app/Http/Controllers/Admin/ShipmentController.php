<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Sales\CreateShipmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreShipmentRequest;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShipmentController extends Controller
{
    public function __construct(
        private readonly CreateShipmentAction $createShipment,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $shipments = Shipment::query()
            ->with(['store:id,name', 'order:id,number'])
            ->when($filters['search'], fn ($q, $search) => $q->where(
                fn ($w) => $w->where('number', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($o) => $o->where('number', 'like', "%{$search}%")),
            ))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Shipment $shipment) => [
                'id' => $shipment->id,
                'number' => $shipment->number,
                'order_number' => $shipment->order->number,
                'order_id' => $shipment->order_id,
                'status' => $shipment->status,
                'carrier_label' => $shipment->carrier_label,
                'tracking_number' => $shipment->tracking_number,
                'total_qty' => $shipment->total_qty,
                'store' => $shipment->store->name,
                'shipped_at' => $shipment->shipped_at?->toDateTimeString(),
            ]);

        return Inertia::render('admin/shipments/index', [
            'shipments' => $shipments,
            'filters' => $filters,
            'statuses' => Shipment::STATUSES,
        ]);
    }

    public function show(Shipment $shipment): Response
    {
        $shipment->load(['items.orderItem:id,sku,name', 'order:id,number,status', 'store:id,name']);

        return Inertia::render('admin/shipments/show', [
            'shipment' => [
                ...$shipment->only(['id', 'number', 'status', 'carrier_code', 'carrier_label', 'tracking_number', 'total_qty', 'notes', 'shipped_at']),
                'store' => $shipment->store->name,
                'order_number' => $shipment->order->number,
                'order_id' => $shipment->order_id,
                'order_status' => $shipment->order->status,
                'delivered_at' => $shipment->delivered_at?->toDateTimeString(),
                'items' => $shipment->items->map(fn ($item) => [
                    'sku' => $item->orderItem->sku,
                    'name' => $item->orderItem->name,
                    'quantity' => $item->quantity,
                ])->values(),
            ],
        ]);
    }

    public function store(StoreShipmentRequest $request): RedirectResponse
    {
        $order = Order::with('items')->findOrFail($request->validated('order_id'));

        $canShip = in_array($order->status, [
            Order::STATUS_PAID, Order::STATUS_PROCESSING, Order::STATUS_INVOICED, Order::STATUS_PARTIALLY_SHIPPED,
        ], true);

        if (! $canShip) {
            return back()->with('error', 'No se puede crear un envío para esta orden.');
        }

        $items = $request->validated('items');

        $this->createShipment->execute($order, $items);

        return to_route('admin.orders.show', $order)->with('success', 'Envío registrado.');
    }

    public function markShipped(Request $request, Shipment $shipment): RedirectResponse
    {
        if ($shipment->status !== Shipment::STATUS_PENDING) {
            return back()->with('error', 'Solo se pueden marcar como enviados los envíos pendientes.');
        }

        $data = $request->validate([
            'carrier_code' => ['nullable', 'string', 'max:50'],
            'carrier_label' => ['nullable', 'string', 'max:100'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
        ]);

        $shipment->update([
            'status' => Shipment::STATUS_SHIPPED,
            'carrier_code' => $data['carrier_code'] ?? $shipment->carrier_code,
            'carrier_label' => $data['carrier_label'] ?? $shipment->carrier_label,
            'tracking_number' => $data['tracking_number'] ?? $shipment->tracking_number,
            'shipped_at' => now(),
        ]);

        $order = $shipment->order;
        $order->transitionTo(Order::STATUS_SHIPPED, "Envío {$shipment->number} despachado.");

        return back()->with('success', 'Envío marcado como enviado.');
    }

    public function markDelivered(Shipment $shipment): RedirectResponse
    {
        if ($shipment->status !== Shipment::STATUS_SHIPPED) {
            return back()->with('error', 'Solo se pueden marcar como entregados los envíos enviados.');
        }

        $order = $shipment->order;

        $shipment->update([
            'status' => Shipment::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        $allDelivered = $order->shipments()
            ->whereNot('status', Shipment::STATUS_DELIVERED)
            ->doesntExist();

        if ($allDelivered) {
            $order->transitionTo(Order::STATUS_COMPLETE, 'Envío completado y entregado.');
        }

        return back()->with('success', 'Envío marcado como entregado.');
    }

    public function cancel(Shipment $shipment): RedirectResponse
    {
        if ($shipment->status !== Shipment::STATUS_PENDING) {
            return back()->with('error', 'Solo se pueden cancelar envíos pendientes.');
        }

        $shipment->update(['status' => Shipment::STATUS_CANCELLED]);

        $order = $shipment->order;
        $order->transitionTo($order->status, "Envío {$shipment->number} cancelado.");

        return back()->with('success', 'Envío cancelado.');
    }
}
