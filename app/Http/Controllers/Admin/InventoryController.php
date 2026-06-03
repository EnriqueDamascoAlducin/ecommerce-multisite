<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Inventory\StockService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustStockRequest;
use App\Models\InventorySource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        private readonly StockService $stock,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $products = Product::query()
            ->with('inventoryStocks')
            ->when($search, fn ($query, $search) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"),
            ))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'physical' => $product->inventoryStocks->sum('physical_qty'),
                'reserved' => $product->inventoryStocks->sum('reserved_qty'),
                'available' => $product->totalAvailableQty(),
                'low_stock' => $product->inventoryStocks->contains(fn ($s) => $s->isLowStock()),
            ]);

        return Inertia::render('admin/inventory/index', [
            'products' => $products,
            'filters' => ['search' => $search],
        ]);
    }

    public function edit(Product $product): Response
    {
        $product->load('inventoryStocks');

        $sources = InventorySource::orderBy('sort_order')->orderBy('name')->get();

        $stockBySource = $sources->map(function (InventorySource $source) use ($product) {
            $stock = $product->inventoryStocks->firstWhere('inventory_source_id', $source->id);

            return [
                'source_id' => $source->id,
                'source_name' => $source->name,
                'physical_qty' => $stock?->physical_qty ?? 0,
                'reserved_qty' => $stock?->reserved_qty ?? 0,
                'available_qty' => $stock?->available_qty ?? 0,
                'manage_stock' => $stock?->manage_stock ?? true,
                'allow_backorders' => $stock?->allow_backorders ?? false,
                'low_stock_threshold' => $stock?->low_stock_threshold,
            ];
        });

        $movements = StockMovement::with('source:id,name')
            ->where('product_id', $product->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (StockMovement $movement) => [
                'id' => $movement->id,
                'type' => $movement->type,
                'quantity' => $movement->quantity,
                'balance_after' => $movement->balance_after,
                'reason' => $movement->reason,
                'source' => $movement->source?->name,
                'created_at' => $movement->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('admin/inventory/edit', [
            'product' => ['id' => $product->id, 'sku' => $product->sku, 'name' => $product->name],
            'stockBySource' => $stockBySource,
            'movements' => $movements,
        ]);
    }

    public function update(AdjustStockRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $source = InventorySource::findOrFail($data['inventory_source_id']);

        $stock = $this->stock->setPhysical(
            $product,
            (int) $data['physical_qty'],
            $source,
            $data['reason'] ?? null,
            $request->user(),
        );

        $stock->update([
            'manage_stock' => $data['manage_stock'] ?? false,
            'allow_backorders' => $data['allow_backorders'] ?? false,
            'low_stock_threshold' => $data['low_stock_threshold'] ?? null,
        ]);

        $this->auditLogger->log('inventory.adjusted', $product, "Stock de {$product->sku} ajustado a {$data['physical_qty']} en {$source->name}");

        return to_route('admin.inventory.edit', $product)->with('success', 'Stock actualizado.');
    }
}
