<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStoreRequest;
use App\Http\Requests\Admin\UpdateStoreRequest;
use App\Models\Media;
use App\Models\Store;
use App\Models\Website;
use App\Services\AuditLogger;
use App\Services\MediaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly MediaService $mediaService,
    ) {}

    public function index(): Response
    {
        $stores = Store::query()
            ->with('website:id,name')
            ->withCount('domains')
            ->orderBy('website_id')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Store $store) => [
                'id' => $store->id,
                'code' => $store->code,
                'name' => $store->name,
                'website' => $store->website->name,
                'is_default' => $store->is_default,
                'is_active' => $store->is_active,
                'domains_count' => $store->domains_count,
            ]);

        return Inertia::render('admin/stores/index', [
            'stores' => $stores,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/stores/create', [
            'websites' => $this->websiteOptions(),
            'availableImages' => $this->availableImages(),
        ]);
    }

    public function store(StoreStoreRequest $request): RedirectResponse
    {
        $store = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $store = Store::create($this->storeAttributes($data));

            $this->applyDefault($store);
            $this->syncDomains($store, $data['domains'] ?? []);
            $this->persistLogo($store, $request);

            return $store;
        });

        $this->auditLogger->log('store.created', $store, "Store {$store->code} creado");

        return to_route('admin.stores.index')->with('success', 'Tienda creada.');
    }

    public function edit(Store $store): Response
    {
        $store->loadMissing('media');
        $logo = $store->primaryMedia('logo');

        return Inertia::render('admin/stores/edit', [
            'store' => [
                ...$store->only(['id', 'website_id', 'code', 'name', 'is_default', 'is_active', 'sort_order']),
                'domains' => $store->domains()->orderByDesc('is_primary')->pluck('host'),
                'logo' => $logo ? ['id' => $logo->id, 'url' => $logo->url] : null,
            ],
            'websites' => $this->websiteOptions(),
            'availableImages' => $this->availableImages(),
        ]);
    }

    public function update(UpdateStoreRequest $request, Store $store): RedirectResponse
    {
        DB::transaction(function () use ($request, $store) {
            $data = $request->validated();
            $store->update($this->storeAttributes($data));

            $this->applyDefault($store);
            $this->syncDomains($store, $data['domains'] ?? []);
            $this->persistLogo($store, $request);
        });

        $this->auditLogger->log('store.updated', $store, "Store {$store->code} actualizado");

        return to_route('admin.stores.index')->with('success', 'Tienda actualizada.');
    }

    public function destroy(Store $store): RedirectResponse
    {
        if ($store->is_default) {
            return back()->with('error', 'No puedes eliminar la tienda por defecto del website.');
        }

        $code = $store->code;
        $store->delete();

        $this->auditLogger->log('store.deleted', null, "Store {$code} eliminado");

        return to_route('admin.stores.index')->with('success', 'Tienda eliminada.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function storeAttributes(array $data): array
    {
        return [
            'website_id' => $data['website_id'],
            'code' => $data['code'],
            'name' => $data['name'],
            'is_default' => $data['is_default'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    /**
     * Garantiza una sola tienda por defecto por website.
     */
    private function applyDefault(Store $store): void
    {
        if ($store->is_default) {
            Store::where('website_id', $store->website_id)
                ->whereKeyNot($store->id)
                ->update(['is_default' => false]);
        }
    }

    /**
     * @param  list<string>  $hosts
     */
    private function syncDomains(Store $store, array $hosts): void
    {
        $store->domains()->delete();

        foreach (array_values(array_unique($hosts)) as $index => $host) {
            $store->domains()->create([
                'host' => $host,
                'is_primary' => $index === 0,
            ]);
        }
    }

    /**
     * Resuelve el logo de la tienda: subida directa, selección de biblioteca,
     * eliminación, o sin cambios.
     */
    private function persistLogo(Store $store, FormRequest $request): void
    {
        if ($request->hasFile('logo_file')) {
            $media = $this->mediaService->store(
                $request->file('logo_file'),
                'logo',
                Media::VISIBILITY_PUBLIC,
                $request->user()?->id,
            );
            $this->auditLogger->log('media.uploaded', $media, "Archivo {$media->name} subido");
            $store->syncMediaCollection([$media->id], 'logo');

            return;
        }

        if ($request->filled('logo_media_id')) {
            $store->syncMediaCollection([(int) $request->input('logo_media_id')], 'logo');

            return;
        }

        if ($request->boolean('remove_logo')) {
            $store->syncMediaCollection([], 'logo');
        }
    }

    /**
     * Imágenes públicas de la biblioteca, para el selector de logo.
     *
     * @return list<array{id: int, url: string, name: string}>
     */
    private function availableImages(): array
    {
        return Media::query()
            ->where('is_image', true)
            ->where('visibility', Media::VISIBILITY_PUBLIC)
            ->latest()
            ->get()
            ->map(fn (Media $media) => [
                'id' => $media->id,
                'url' => $media->url,
                'name' => $media->name,
            ])
            ->all();
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    private function websiteOptions()
    {
        return Website::orderBy('sort_order')->get(['id', 'name'])
            ->map(fn (Website $website) => ['id' => $website->id, 'name' => $website->name]);
    }
}
