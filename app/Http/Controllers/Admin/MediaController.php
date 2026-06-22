<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMediaRequest;
use App\Http\Requests\Admin\UpdateMediaRequest;
use App\Models\Media;
use App\Services\AuditLogger;
use App\Services\MediaService;
use App\Services\MediaUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly MediaUsageService $mediaUsage,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $usedMediaIds = $this->mediaUsage->usedMediaIds($filters['context']);

        $query = Media::query()
            ->when($filters['name'] !== '', function ($query) use ($filters): void {
                $search = addcslashes($filters['name'], '%_\\');

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('filename', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('alt', 'like', "%{$search}%");
                });
            })
            ->when($filters['type'] === 'images', fn ($query) => $query->where('is_image', true))
            ->when($filters['type'] === 'files', fn ($query) => $query->where('is_image', false))
            ->when($filters['context'] !== 'all' || $filters['usage'] === 'used', function ($query) use ($usedMediaIds): void {
                $query->whereIn('id', $usedMediaIds ?: [0]);
            })
            ->when($filters['usage'] === 'unused', function ($query) use ($usedMediaIds): void {
                if ($usedMediaIds !== []) {
                    $query->whereNotIn('id', $usedMediaIds);
                }
            });

        $media = $query
            ->latest()
            ->paginate(24)
            ->withQueryString();

        $usageMap = $this->mediaUsage->usagesFor($media->getCollection(), $filters['context']);

        $media->setCollection(
            $media->getCollection()->map(fn (Media $media): array => $this->present($media, $usageMap[$media->id] ?? [])),
        );

        return Inertia::render('admin/media/index', [
            'media' => $media,
            'filters' => $filters,
            'filterOptions' => [
                'types' => [
                    ['value' => 'all', 'label' => 'Todos'],
                    ['value' => 'images', 'label' => 'Imagenes'],
                    ['value' => 'files', 'label' => 'Archivos'],
                ],
                'usages' => [
                    ['value' => 'all', 'label' => 'Todos'],
                    ['value' => 'used', 'label' => 'En uso'],
                    ['value' => 'unused', 'label' => 'Sin uso'],
                ],
                'contexts' => [
                    ['value' => 'all', 'label' => 'Todos los usos'],
                    ['value' => 'products', 'label' => 'Productos'],
                    ['value' => 'pages', 'label' => 'Paginas'],
                    ['value' => 'sections', 'label' => 'Secciones'],
                    ['value' => 'seo', 'label' => 'SEO'],
                    ['value' => 'header', 'label' => 'Cintillo'],
                    ['value' => 'stores', 'label' => 'Tiendas'],
                    ['value' => 'websites', 'label' => 'Websites'],
                    ['value' => 'categories', 'label' => 'Categorias'],
                ],
            ],
        ]);
    }

    public function store(StoreMediaRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $collection = $validated['collection'] ?? 'default';
        $visibility = $validated['visibility'] ?? Media::VISIBILITY_PUBLIC;

        $uploaded = [];

        foreach ($request->file('files') as $file) {
            $media = $this->mediaService->store($file, $collection, $visibility, $request->user()?->id);
            $this->auditLogger->log('media.uploaded', $media, "Archivo {$media->name} subido");
            $uploaded[] = $media;
        }

        if ($request->wantsJson()) {
            $first = $uploaded[0] ?? null;

            return response()->json(
                $first ? $this->present($first) : ['id' => null],
            );
        }

        return back()->with('success', 'Archivos subidos.');
    }

    public function update(UpdateMediaRequest $request, Media $media): RedirectResponse
    {
        $media->update($request->validated());

        return back()->with('success', 'Medio actualizado.');
    }

    public function destroy(Media $media): RedirectResponse
    {
        $name = $media->name;
        $this->mediaService->delete($media);

        $this->auditLogger->log('media.deleted', null, "Archivo {$name} eliminado");

        return back()->with('success', 'Medio eliminado.');
    }

    /**
     * Descarga de archivos privados mediante URL firmada (productos descargables, etc.).
     */
    public function download(Media $media): StreamedResponse
    {
        abort_unless($media->isPrivate(), 404);

        return Storage::disk($media->disk)->download($media->path, $media->name);
    }

    /**
     * @return array{name: string, type: string, usage: string, context: string}
     */
    private function filters(Request $request): array
    {
        $type = $request->string('type')->toString();
        $usage = $request->string('usage')->toString();
        $context = $request->string('context')->toString();

        return [
            'name' => $request->string('name')->trim()->toString(),
            'type' => in_array($type, ['images', 'files'], true) ? $type : 'all',
            'usage' => in_array($usage, ['used', 'unused'], true) ? $usage : 'all',
            'context' => in_array($context, ['products', 'pages', 'sections', 'seo', 'header', 'stores', 'websites', 'categories'], true) ? $context : 'all',
        ];
    }

    /**
     * @param  list<array{context: string, label: string, title: string, description: string|null}>  $usages
     * @return array<string, mixed>
     */
    private function present(Media $media, array $usages = []): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'url' => $media->url,
            'is_image' => $media->is_image,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'visibility' => $media->visibility,
            'title' => $media->title,
            'alt' => $media->alt,
            'created_at' => $media->created_at?->toDateString(),
            'usages' => $usages,
        ];
    }
}
