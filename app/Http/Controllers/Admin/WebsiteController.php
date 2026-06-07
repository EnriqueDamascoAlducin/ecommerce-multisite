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
        $this->persistFavicon($website, $request);

        $this->auditLogger->log('website.created', $website, "Website {$website->code} creado");

        return to_route('admin.websites.index')->with('success', 'Website creado.');
    }

    public function edit(Website $website): Response
    {
        $website->loadMissing('media');
        $logo = $website->primaryMedia('logo');
        $favicon = $website->primaryMedia('favicon');

        return Inertia::render('admin/websites/edit', [
            'website' => [
                ...$website->only(['id', 'code', 'name', 'is_default', 'sort_order']),
                'logo' => $logo ? ['id' => $logo->id, 'url' => $logo->url] : null,
                'favicon' => $favicon ? ['id' => $favicon->id, 'url' => $favicon->url] : null,
            ],
            'availableImages' => $this->availableImages(),
        ]);
    }

    public function update(UpdateWebsiteRequest $request, Website $website): RedirectResponse
    {
        $website->update($request->safe()->only(['code', 'name', 'is_default', 'sort_order']));

        $this->persistLogo($website, $request);
        $this->persistFavicon($website, $request);

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
        $this->persistSingleMedia($website, $request, 'logo', 'logo_file', 'logo_media_id', 'remove_logo');
    }

    private function persistFavicon(Website $website, FormRequest $request): void
    {
        $this->persistSingleMedia($website, $request, 'favicon', 'favicon_file', 'favicon_media_id', 'remove_favicon');
    }

    private function persistSingleMedia(
        Website $website,
        FormRequest $request,
        string $collection,
        string $fileInput,
        string $mediaInput,
        string $removeInput,
    ): void {
        if ($request->hasFile($fileInput)) {
            $media = $this->mediaService->store(
                $request->file($fileInput),
                $collection,
                Media::VISIBILITY_PUBLIC,
                $request->user()?->id,
            );
            $this->auditLogger->log('media.uploaded', $media, "Archivo {$media->name} subido");
            $website->syncMediaCollection([$media->id], $collection);

            return;
        }

        if ($request->filled($mediaInput)) {
            $website->syncMediaCollection([(int) $request->input($mediaInput)], $collection);

            return;
        }

        if ($request->boolean($removeInput)) {
            $website->syncMediaCollection([], $collection);
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
