<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInventorySourceRequest;
use App\Http\Requests\Admin\UpdateInventorySourceRequest;
use App\Models\InventorySource;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InventorySourceController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        $sources = InventorySource::query()
            ->withCount('stocks')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (InventorySource $source) => [
                'id' => $source->id,
                'code' => $source->code,
                'name' => $source->name,
                'is_default' => $source->is_default,
                'is_active' => $source->is_active,
                'stocks_count' => $source->stocks_count,
            ]);

        return Inertia::render('admin/inventory/sources/index', [
            'sources' => $sources,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/inventory/sources/create');
    }

    public function store(StoreInventorySourceRequest $request): RedirectResponse
    {
        $source = InventorySource::create($this->attributes($request->validated()));
        $this->applyDefault($source);

        $this->auditLogger->log('inventory_source.created', $source, "Almacén {$source->code} creado");

        return to_route('admin.inventory-sources.index')->with('success', 'Almacén creado.');
    }

    public function edit(InventorySource $inventorySource): Response
    {
        return Inertia::render('admin/inventory/sources/edit', [
            'source' => $inventorySource->only(['id', 'code', 'name', 'is_default', 'is_active', 'sort_order']),
        ]);
    }

    public function update(UpdateInventorySourceRequest $request, InventorySource $inventorySource): RedirectResponse
    {
        $inventorySource->update($this->attributes($request->validated()));
        $this->applyDefault($inventorySource);

        $this->auditLogger->log('inventory_source.updated', $inventorySource, "Almacén {$inventorySource->code} actualizado");

        return to_route('admin.inventory-sources.index')->with('success', 'Almacén actualizado.');
    }

    public function destroy(InventorySource $inventorySource): RedirectResponse
    {
        if ($inventorySource->is_default) {
            return back()->with('error', 'No puedes eliminar el almacén por defecto.');
        }

        $code = $inventorySource->code;
        $inventorySource->delete();

        $this->auditLogger->log('inventory_source.deleted', null, "Almacén {$code} eliminado");

        return to_route('admin.inventory-sources.index')->with('success', 'Almacén eliminado.');
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
            'is_default' => $data['is_default'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    /**
     * Garantiza un único almacén por defecto.
     */
    private function applyDefault(InventorySource $source): void
    {
        if ($source->is_default) {
            InventorySource::whereKeyNot($source->id)->update(['is_default' => false]);
        }
    }
}
