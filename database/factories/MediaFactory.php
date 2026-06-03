<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'disk' => 'public',
            'directory' => 'default/2026/06',
            'filename' => Str::uuid()->toString().'.jpg',
            'name' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => fake()->numberBetween(1000, 500000),
            'is_image' => true,
            'visibility' => Media::VISIBILITY_PUBLIC,
        ];
    }

    public function private(): static
    {
        return $this->state(fn () => [
            'disk' => 'local',
            'visibility' => Media::VISIBILITY_PRIVATE,
            'is_image' => false,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'name' => fake()->word().'.pdf',
        ]);
    }
}
