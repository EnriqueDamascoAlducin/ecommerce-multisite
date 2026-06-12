<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMediaRequest;
use App\Http\Requests\Admin\UpdateMediaRequest;
use App\Models\Media;
use App\Services\AuditLogger;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(): Response
    {
        $media = Media::query()
            ->latest()
            ->paginate(24)
            ->through(fn (Media $media) => $this->present($media));

        return Inertia::render('admin/media/index', [
            'media' => $media,
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
     * @return array<string, mixed>
     */
    private function present(Media $media): array
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
        ];
    }
}
