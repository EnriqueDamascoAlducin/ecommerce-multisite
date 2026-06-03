<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Almacena y elimina archivos de la biblioteca de medios. Usa el disco "public"
 * para medios públicos y "local" (privado) para descargables. Compatible con S3
 * cambiando el disco vía configuración.
 */
class MediaService
{
    public function store(
        UploadedFile $file,
        string $collection = 'default',
        string $visibility = Media::VISIBILITY_PUBLIC,
        ?int $uploadedBy = null,
    ): Media {
        $disk = $this->diskFor($visibility);
        $directory = $collection.'/'.date('Y/m');
        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension();
        $filename = Str::uuid()->toString().($extension ? '.'.$extension : '');

        $file->storeAs($directory, $filename, ['disk' => $disk]);

        $mime = $file->getClientMimeType();

        return Media::create([
            'disk' => $disk,
            'directory' => $directory,
            'filename' => $filename,
            'name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'extension' => $extension,
            'size' => $file->getSize() ?: 0,
            'is_image' => str_starts_with((string) $mime, 'image/'),
            'visibility' => $visibility,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /**
     * Elimina el archivo del disco y el registro (los vínculos se borran en cascada).
     */
    public function delete(Media $media): void
    {
        Storage::disk($media->disk)->delete($media->path);

        $media->delete();
    }

    private function diskFor(string $visibility): string
    {
        return $visibility === Media::VISIBILITY_PRIVATE ? 'local' : 'public';
    }
}
