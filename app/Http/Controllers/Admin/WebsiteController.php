<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWebsiteRequest;
use App\Http\Requests\Admin\UpdateWebsiteRequest;
use App\Models\Media;
use App\Models\Website;
use App\Services\AuditLogger;
use App\Services\MediaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly MediaService $mediaService,
    ) {}

    public function index(): Response
    {
        $websites = Website::query()
            ->withCount('stores')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Website $website) => [
                'id' => $website->id,
                'code' => $website->code,
                'name' => $website->name,
                'is_default' => $website->is_default,
                'stores_count' => $website->stores_count,
            ]);

        return Inertia::render('admin/websites/index', [
            'websites' => $websites,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/websites/create', [
            'availableImages' => $this->availableImages(),
        ]);
    }

    public function store(StoreWebsiteRequest $request): RedirectResponse
    {
        $website = Website::create($request->safe()->only(['code', 'name', 'is_default', 'sort_order']));

        $this->persistLogo($website, $request);

        $this->auditLogger->log('website.created', $website, "Website {$website->code} creado");

        return to_route('admin.websites.index')->with('success', 'Website creado.');
    }

    public function edit(Website $website): Response
    {
        $logo = $website->loadMissing('media')->primaryMedia('logo');

        return Inertia::render('admin/websites/edit', [
            'website' => [
                ...$website->only(['id', 'code', 'name', 'is_default', 'sort_order']),
                'logo' => $logo ? ['id' => $logo->id, 'url' => $logo->url] : null,
            ],
            'availableImages' => $this->availableImages(),
        ]);
    }

    public function update(UpdateWebsiteRequest $request, Website $website): RedirectResponse
    {
        $website->update($request->safe()->only(['code', 'name', 'is_default', 'sort_order']));

        $this->persistLogo($website, $request);

        $this->auditLogger->log('website.updated', $website, "Website {$website->code} actualizado");

        return to_route('admin.websites.index')->with('success', 'Website actualizado.');
    }

    public function destroy(Website $website): RedirectResponse
    {
        if ($website->is_default) {
            return back()->with('error', 'No puedes eliminar el website por defecto.');
        }

        $code = $website->code;
        $website->delete();

        $this->auditLogger->log('website.deleted', null, "Website {$code} eliminado");

        return to_route('admin.websites.index')->with('success', 'Website eliminado.');
    }

    /**
     * Resuelve el logo del website: subida directa, selección de la biblioteca,
     * eliminación, o sin cambios.
     */
    private function persistLogo(Website $website, FormRequest $request): void
    {
        if ($request->hasFile('logo_file')) {
            $media = $this->mediaService->store(
                $request->file('logo_file'),
                'logo',
                Media::VISIBILITY_PUBLIC,
                $request->user()?->id,
            );
            $this->auditLogger->log('media.uploaded', $media, "Archivo {$media->name} subido");
            $website->syncMediaCollection([$media->id], 'logo');

            return;
        }

        if ($request->filled('logo_media_id')) {
            $website->syncMediaCollection([(int) $request->input('logo_media_id')], 'logo');

            return;
        }

        if ($request->boolean('remove_logo')) {
            $website->syncMediaCollection([], 'logo');
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
}
