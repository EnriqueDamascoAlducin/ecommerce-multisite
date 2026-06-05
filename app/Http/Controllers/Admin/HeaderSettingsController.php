<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HeaderSettingsRequest;
use App\Models\Media;
use App\Models\Website;
use App\Models\WebsiteHeaderSettings;
use App\Services\AuditLogger;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class HeaderSettingsController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly MediaService $mediaService,
    ) {}

    /**
     * Sube una imagen del cintillo a la biblioteca (disco público) y devuelve
     * su id y URL para guardarlos en el bloque correspondiente.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg,gif', 'max:2048'],
        ]);

        $media = $this->mediaService->store($request->file('file'), 'cintillo', Media::VISIBILITY_PUBLIC, $request->user()?->id);

        return response()->json(['id' => $media->id, 'url' => $media->url]);
    }

    public function edit(Request $request): Response
    {
        $website = $this->resolveWebsite($request->integer('website_id'));

        return Inertia::render('admin/header-settings/index', [
            'websites' => $this->websiteOptions(),
            'currentWebsiteId' => $website?->id,
            'settings' => $website ? $this->present($website) : null,
            'platforms' => WebsiteHeaderSettings::SOCIAL_PLATFORMS,
        ]);
    }

    public function update(HeaderSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        WebsiteHeaderSettings::updateOrCreate(
            ['website_id' => $data['website_id']],
            [
                'cintillo_enabled' => $data['cintillo_enabled'] ?? false,
                'cintillo_show_on_mobile' => $data['cintillo_show_on_mobile'] ?? true,
                'cintillo_blocks' => $this->sanitizeBlocks($data['cintillo_blocks'] ?? []),
                'cintillo_text_color' => $data['cintillo_text_color'],
                'cintillo_background_color' => $data['cintillo_background_color'],
                'header_text_color' => $data['header_text_color'] ?? null,
                'header_background_color' => $data['header_background_color'] ?? null,
                'menu_text_color' => $data['menu_text_color'] ?? null,
                'menu_background_color' => $data['menu_background_color'] ?? null,
            ],
        );

        $this->auditLogger->log('header_settings.updated', null, "Configuracion del header del website {$data['website_id']} actualizada");

        return to_route('admin.header-settings.edit', ['website_id' => $data['website_id']])
            ->with('success', 'Configuración del header actualizada.');
    }

    /**
     * Normaliza los bloques del cintillo (máx 3): conserva los de texto con
     * contenido y los de redes con al menos un enlace válido; descarta vacíos.
     *
     * @param  list<array{type?: string, text?: string|null, social?: list<array{platform?: string, url?: string|null}>}>  $blocks
     * @return list<array<string, mixed>>
     */
    private function sanitizeBlocks(array $blocks): array
    {
        return collect($blocks)
            ->take(3)
            ->map(function ($block) {
                $type = $block['type'] ?? null;

                if ($type === WebsiteHeaderSettings::TYPE_SOCIAL) {
                    $social = $this->sanitizeSocial($block['social'] ?? []);

                    return $social === [] ? null : ['type' => WebsiteHeaderSettings::TYPE_SOCIAL, 'social' => $social];
                }

                if ($type === WebsiteHeaderSettings::TYPE_IMAGE) {
                    $url = trim((string) ($block['url'] ?? ''));

                    return $url === '' ? null : array_filter([
                        'type' => WebsiteHeaderSettings::TYPE_IMAGE,
                        'url' => $url,
                        'media_id' => $block['media_id'] ?? null,
                        'alt' => $block['alt'] ?? null,
                        'link' => $block['link'] ?? null,
                    ], fn ($value) => $value !== null);
                }

                $text = trim((string) ($block['text'] ?? ''));

                return $text === '' ? null : ['type' => WebsiteHeaderSettings::TYPE_TEXT, 'text' => $text];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Conserva solo las redes con plataforma soportada y URL no vacía.
     *
     * @param  list<array{platform?: string, url?: string|null}>  $social
     * @return list<array{platform: string, url: string}>
     */
    private function sanitizeSocial(array $social): array
    {
        return collect($social)
            ->filter(fn ($row) => ! empty($row['url']) && in_array($row['platform'] ?? null, WebsiteHeaderSettings::SOCIAL_PLATFORMS, true))
            ->map(fn ($row) => ['platform' => $row['platform'], 'url' => $row['url']])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Website $website): array
    {
        $settings = WebsiteHeaderSettings::firstWhere('website_id', $website->id);

        return [
            'cintillo_enabled' => $settings?->cintillo_enabled ?? false,
            'cintillo_show_on_mobile' => $settings?->cintillo_show_on_mobile ?? true,
            'cintillo_blocks' => $settings?->cintillo_blocks ?? [],
            'cintillo_text_color' => $settings?->cintillo_text_color ?? '#ffffff',
            'cintillo_background_color' => $settings?->cintillo_background_color ?? '#111827',
            'header_text_color' => $settings?->header_text_color,
            'header_background_color' => $settings?->header_background_color,
            'menu_text_color' => $settings?->menu_text_color,
            'menu_background_color' => $settings?->menu_background_color,
        ];
    }

    private function resolveWebsite(int $websiteId): ?Website
    {
        return ($websiteId ? Website::find($websiteId) : null)
            ?? Website::where('is_default', true)->first()
            ?? Website::orderBy('sort_order')->first();
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    private function websiteOptions(): Collection
    {
        return Website::orderBy('sort_order')->get(['id', 'name'])
            ->map(fn (Website $website) => ['id' => $website->id, 'label' => $website->name]);
    }
}
