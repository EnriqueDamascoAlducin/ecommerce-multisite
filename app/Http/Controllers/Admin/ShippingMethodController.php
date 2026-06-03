<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreShippingMethodRequest;
use App\Http\Requests\Admin\UpdateShippingMethodRequest;
use App\Models\ShippingMethod;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ShippingMethodController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        $methods = ShippingMethod::query()
            ->withCount('storeMethods')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ShippingMethod $method) => [
                'id' => $method->id,
                'code' => $method->code,
                'name' => $method->name,
                'type' => $method->type,
                'is_active' => $method->is_active,
                'stores_count' => $method->store_methods_count,
            ]);

        return Inertia::render('admin/shipping/index', [
            'methods' => $methods,
            'types' => ShippingMethod::TYPES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/shipping/create', [
            'types' => ShippingMethod::TYPES,
        ]);
    }

    public function store(StoreShippingMethodRequest $request): RedirectResponse
    {
        $method = ShippingMethod::create($this->attributes($request->validated()));

        $this->auditLogger->log('shipping_method.created', $method, "Método de envío {$method->code} creado");

        return to_route('admin.shipping.index')->with('success', 'Método de envío creado.');
    }

    public function edit(ShippingMethod $shipping): Response
    {
        return Inertia::render('admin/shipping/edit', [
            'method' => $shipping->only(['id', 'code', 'name', 'type', 'is_active', 'sort_order']),
            'types' => ShippingMethod::TYPES,
        ]);
    }

    public function update(UpdateShippingMethodRequest $request, ShippingMethod $shipping): RedirectResponse
    {
        $shipping->update($this->attributes($request->validated()));

        $this->auditLogger->log('shipping_method.updated', $shipping, "Método de envío {$shipping->code} actualizado");

        return to_route('admin.shipping.index')->with('success', 'Método de envío actualizado.');
    }

    public function destroy(ShippingMethod $shipping): RedirectResponse
    {
        $code = $shipping->code;
        $shipping->delete();

        $this->auditLogger->log('shipping_method.deleted', null, "Método de envío {$code} eliminado");

        return to_route('admin.shipping.index')->with('success', 'Método de envío eliminado.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'is_active' => $data['is_active'] ?? false,
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }
}
