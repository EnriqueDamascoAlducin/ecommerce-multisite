<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreShippingConfigRequest;
use App\Models\ShippingMethod;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StoreShippingController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function edit(Request $request): Response
    {
        $store = $this->resolveStore($request->integer('store_id'));

        $configured = $store
            ? StoreShippingMethod::with('rates')->where('store_id', $store->id)->get()->keyBy('shipping_method_id')
            : collect();

        $methods = ShippingMethod::orderBy('sort_order')->orderBy('name')->get()
            ->map(function (ShippingMethod $method) use ($configured) {
                $ssm = $configured->get($method->id);

                return [
                    'shipping_method_id' => $method->id,
                    'code' => $method->code,
                    'name' => $method->name,
                    'type' => $method->type,
                    'enabled' => $ssm !== null && $ssm->is_active,
                    'label' => $ssm?->label,
                    'amount' => $ssm?->rates->first()?->amount,
                    'free_over' => $ssm?->free_over,
                    'min_subtotal' => $ssm?->min_subtotal,
                    'max_subtotal' => $ssm?->max_subtotal,
                    'countries' => $ssm && is_array($ssm->countries) ? implode(', ', $ssm->countries) : null,
                ];
            });

        return Inertia::render('admin/shipping/stores', [
            'stores' => $this->storeOptions(),
            'currentStoreId' => $store?->id,
            'methods' => $methods,
        ]);
    }

    public function update(StoreShippingConfigRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $store = Store::findOrFail($data['store_id']);

        DB::transaction(function () use ($data, $store) {
            foreach ($data['methods'] ?? [] as $row) {
                $methodId = (int) $row['shipping_method_id'];

                if (empty($row['enabled'])) {
                    StoreShippingMethod::where('store_id', $store->id)
                        ->where('shipping_method_id', $methodId)
                        ->delete();

                    continue;
                }

                $ssm = StoreShippingMethod::updateOrCreate(
                    ['store_id' => $store->id, 'shipping_method_id' => $methodId],
                    [
                        'label' => $row['label'] ?? null,
                        'is_active' => true,
                        'free_over' => $row['free_over'] ?? null,
                        'min_subtotal' => $row['min_subtotal'] ?? null,
                        'max_subtotal' => $row['max_subtotal'] ?? null,
                        'countries' => $this->parseCountries($row['countries'] ?? null),
                    ],
                );

                // Tarifa base única (los tramos por subtotal quedan para más adelante).
                $ssm->rates()->delete();
                $ssm->rates()->create([
                    'min_subtotal' => 0,
                    'max_subtotal' => null,
                    'amount' => $row['amount'] ?? 0,
                    'sort_order' => 0,
                ]);
            }
        });

        $this->auditLogger->log('store_shipping.updated', $store, "Envíos de la tienda {$store->code} actualizados");

        return to_route('admin.shipping-stores.edit', ['store_id' => $store->id])
            ->with('success', 'Envíos de la tienda actualizados.');
    }

    /**
     * @return list<string>|null
     */
    private function parseCountries(?string $value): ?array
    {
        if (! $value) {
            return null;
        }

        $codes = collect(explode(',', $value))
            ->map(fn (string $c) => strtoupper(trim($c)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $codes === [] ? null : $codes;
    }

    private function resolveStore(int $storeId): ?Store
    {
        return ($storeId ? Store::find($storeId) : null)
            ?? Store::orderBy('website_id')->orderBy('sort_order')->first();
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    private function storeOptions()
    {
        return Store::with('website:id,name')->orderBy('website_id')->orderBy('sort_order')->get()
            ->map(fn (Store $store) => ['id' => $store->id, 'label' => "{$store->website->name} / {$store->name}"]);
    }
}
