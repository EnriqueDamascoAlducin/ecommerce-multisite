<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PRIVATE = 'private';

    /** @var list<string> */
    protected $fillable = [
        'disk', 'directory', 'filename', 'name', 'mime_type', 'extension',
        'size', 'is_image', 'visibility', 'title', 'alt', 'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_image' => 'boolean',
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Ruta relativa dentro del disco.
     */
    protected function path(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->directory, '/').'/'.$this->filename);
    }

    public function isPrivate(): bool
    {
        return $this->visibility === self::VISIBILITY_PRIVATE;
    }

    /**
     * URL pública directa, o ruta de descarga firmada para archivos privados.
     */
    protected function url(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->isPrivate()) {
                return URL::signedRoute('media.download', $this);
            }

            return Storage::disk($this->disk)->url($this->path);
        });
    }
}
