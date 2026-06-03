<?php

namespace App\Models\Concerns;

use App\Models\Media;
use App\Models\Mediable;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

/**
 * Da a cualquier modelo una relación polimórfica con la biblioteca de medios,
 * con colecciones (p. ej. "gallery"), imagen principal y ordenamiento.
 */
trait HasMedia
{
    /**
     * @return MorphToMany<Media, $this>
     */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable')
            ->using(Mediable::class)
            ->withPivot(['collection', 'is_primary', 'sort_order'])
            ->withTimestamps()
            ->orderBy('mediables.sort_order');
    }

    /**
     * @return Collection<int, Media>
     */
    public function mediaInCollection(string $collection = 'default'): Collection
    {
        return $this->media->filter(fn (Media $media) => $media->pivot->collection === $collection)->values();
    }

    public function primaryMedia(string $collection = 'default'): ?Media
    {
        $items = $this->mediaInCollection($collection);

        return $items->firstWhere('pivot.is_primary', true) ?? $items->first();
    }

    public function attachMedia(Media $media, string $collection = 'default', bool $isPrimary = false, int $sortOrder = 0): void
    {
        $this->media()->attach($media->getKey(), [
            'collection' => $collection,
            'is_primary' => $isPrimary,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * Reemplaza el contenido de una colección con los medios dados, en orden.
     * El primero se marca como principal.
     *
     * @param  list<int>  $mediaIds
     */
    public function syncMediaCollection(array $mediaIds, string $collection = 'default'): void
    {
        $existing = $this->media()->wherePivot('collection', $collection)->pluck('media.id')->all();
        $this->media()->detach($existing);

        foreach (array_values($mediaIds) as $index => $mediaId) {
            $this->media()->attach($mediaId, [
                'collection' => $collection,
                'is_primary' => $index === 0,
                'sort_order' => $index,
            ]);
        }
    }
}
