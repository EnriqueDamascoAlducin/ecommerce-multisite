<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Store\ScopedConfigService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HeaderSettingsRequest;
use App\Models\Media;
use App\Models\Store;
use App\Models\StoreConfiguration;
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
use JsonException;

class HeaderSettingsController extends Controller
{
    private const CINTILLO_CONFIG_KEY = 'header.cintillo';

    private const MODE_WEBSITE = 'website';

    private const MODE_INHERIT = 'inherit';

    private const MODE_CUSTOM = 'custom';

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
            'file' => ['required', 'image:allow_svg', 'mimes:png,jpg,jpeg,webp,svg,gif', 'max:2048'],
        ]);

        $media = $this->mediaService->store($request->file('file'), 'cintillo', Media::VISIBILITY_PUBLIC, $request->user()?->id);

        return response()->json(['id' => $media->id, 'url' => $media->url]);
    }

    public function edit(Request $request): Response
    {
        $website = $this->resolveWebsite($request->integer('website_id'));
        $store = $website ? $this->resolveStore($website, $request->integer('store_id')) : null;
        $baseSettings = $website ? $this->present($website) : null;
        $storeOverride = $store ? $this->storeCintilloOverride($store) : null;

        return Inertia::render('admin/header-settings/index', [
            'websites' => $this->websiteOptions(),
            'stores' => $website ? $this->storeOptions($website) : [],
            'currentWebsiteId' => $website?->id,
            'currentStoreId' => $store?->id,
            'cintilloMode' => $store ? ($storeOverride ? self::MODE_CUSTOM : self::MODE_INHERIT) : self::MODE_WEBSITE,
            'settings' => $website ? $this->present($website, $storeOverride) : null,
            'inheritedCintillo' => $baseSettings ? $this->cintilloFromSettings($baseSettings) : null,
            'platforms' => WebsiteHeaderSettings::SOCIAL_PLATFORMS,
        ]);
    }

    public function update(HeaderSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $website = Website::findOrFail($data['website_id']);
        $store = isset($data['store_id']) ? $this->resolveStore($website, (int) $data['store_id']) : null;

        if ($store) {
            if (($data['cintillo_mode'] ?? self::MODE_INHERIT) === self::MODE_INHERIT) {
                $this->deleteStoreCintilloOverride($store);
                $this->auditLogger->log('header_settings.updated', null, "Cintillo de tienda {$store->id} configurado para heredar del website {$website->id}");

                return to_route('admin.header-settings.edit', ['website_id' => $website->id, 'store_id' => $store->id])
                    ->with('success', 'La tienda ahora hereda el cintillo del website.');
            }

            StoreConfiguration::updateOrCreate(
                [
                    'scope' => ScopedConfigService::SCOPE_STORE,
                    'scope_id' => $store->id,
                    'key' => self::CINTILLO_CONFIG_KEY,
                ],
                ['value' => json_encode($this->cintilloPayloadFromData($data), JSON_THROW_ON_ERROR)],
            );

            $this->auditLogger->log('header_settings.updated', null, "Cintillo personalizado de tienda {$store->id} actualizado", [
                'website_id' => $website->id,
                'store_id' => $store->id,
            ]);

            return to_route('admin.header-settings.edit', ['website_id' => $website->id, 'store_id' => $store->id])
                ->with('success', 'Cintillo de tienda actualizado.');
        }

        WebsiteHeaderSettings::updateOrCreate(
            ['website_id' => $website->id],
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
                'footer_settings' => $this->sanitizeFooter($data['footer'] ?? []),
            ],
        );

        $this->auditLogger->log('header_settings.updated', null, "Configuracion del header del website {$website->id} actualizada");

        return to_route('admin.header-settings.edit', ['website_id' => $website->id])
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
                    $images = $this->sanitizeCintilloImages($block['images'] ?? []);

                    if ($images === []) {
                        $images = $this->sanitizeCintilloImages([[
                            'url' => $block['url'] ?? null,
                            'media_id' => $block['media_id'] ?? null,
                            'alt' => $block['alt'] ?? null,
                            'link' => $block['link'] ?? null,
                        ]]);
                    }

                    return $images === [] ? null : [
                        'type' => WebsiteHeaderSettings::TYPE_IMAGE,
                        'images' => $images,
                    ];
                }

                $text = trim((string) ($block['text'] ?? ''));

                return $text === '' ? null : ['type' => WebsiteHeaderSettings::TYPE_TEXT, 'text' => $text];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{url: string, media_id?: int, alt?: string, link?: string}>
     */
    private function sanitizeCintilloImages(mixed $images): array
    {
        if (! is_array($images)) {
            return [];
        }

        return collect($images)
            ->take(6)
            ->filter(fn ($image) => is_array($image) && trim((string) ($image['url'] ?? '')) !== '')
            ->map(fn ($image) => array_filter([
                'url' => trim((string) $image['url']),
                'media_id' => $image['media_id'] ?? null,
                'alt' => trim((string) ($image['alt'] ?? '')) ?: null,
                'link' => trim((string) ($image['link'] ?? '')) ?: null,
            ], fn ($value) => $value !== null))
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
    private function present(Website $website, ?array $cintilloOverride = null): array
    {
        $settings = WebsiteHeaderSettings::firstWhere('website_id', $website->id);
        $cintillo = $cintilloOverride ?? [
            'enabled' => $settings?->cintillo_enabled ?? false,
            'show_on_mobile' => $settings?->cintillo_show_on_mobile ?? true,
            'blocks' => $settings?->cintillo_blocks ?? [],
            'text_color' => $settings?->cintillo_text_color ?? '#ffffff',
            'background_color' => $settings?->cintillo_background_color ?? '#111827',
        ];

        return [
            'cintillo_enabled' => $cintillo['enabled'],
            'cintillo_show_on_mobile' => $cintillo['show_on_mobile'],
            'cintillo_blocks' => $cintillo['blocks'],
            'cintillo_text_color' => $cintillo['text_color'],
            'cintillo_background_color' => $cintillo['background_color'],
            'header_text_color' => $settings?->header_text_color,
            'header_background_color' => $settings?->header_background_color,
            'menu_text_color' => $settings?->menu_text_color,
            'menu_background_color' => $settings?->menu_background_color,
            'footer' => $this->footerPayload($settings?->footer_settings, $website),
        ];
    }

    /**
     * @param  array<string, mixed>  $footer
     * @return array{enabled: bool, description: string, copyright: string, background_color: string|null, text_color: string|null, columns: list<array{title: string, title_color: string|null, link_color: string|null, links: list<array{label: string, url: string}>}>, contact: list<array{label: string, value: string}>, social: list<array{platform: string, url: string}>}
     */
    private function sanitizeFooter(array $footer): array
    {
        return [
            'enabled' => (bool) ($footer['enabled'] ?? true),
            'description' => trim((string) ($footer['description'] ?? '')),
            'copyright' => trim((string) ($footer['copyright'] ?? '')),
            'background_color' => $footer['background_color'] ?? null,
            'text_color' => $footer['text_color'] ?? null,
            'columns' => $this->sanitizeFooterColumns($footer['columns'] ?? []),
            'contact' => $this->sanitizeFooterContact($footer['contact'] ?? []),
            'social' => $this->sanitizeSocial($footer['social'] ?? []),
        ];
    }

    /**
     * @return list<array{title: string, title_color: string|null, link_color: string|null, links: list<array{label: string, url: string}>}>
     */
    private function sanitizeFooterColumns(mixed $columns): array
    {
        if (! is_array($columns)) {
            return [];
        }

        return collect($columns)
            ->take(4)
            ->map(function ($column) {
                if (! is_array($column)) {
                    return null;
                }

                $links = collect($column['links'] ?? [])
                    ->take(8)
                    ->filter(fn ($link) => is_array($link) && trim((string) ($link['label'] ?? '')) !== '' && trim((string) ($link['url'] ?? '')) !== '')
                    ->map(fn ($link) => [
                        'label' => trim((string) $link['label']),
                        'url' => trim((string) $link['url']),
                    ])
                    ->values()
                    ->all();

                $title = trim((string) ($column['title'] ?? ''));
                $titleColor = trim((string) ($column['title_color'] ?? '')) ?: null;
                $linkColor = trim((string) ($column['link_color'] ?? '')) ?: null;

                if ($title === '' && $links === []) {
                    return null;
                }

                return [
                    'title' => $title,
                    'title_color' => $titleColor,
                    'link_color' => $linkColor,
                    'links' => $links,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function sanitizeFooterContact(mixed $contact): array
    {
        if (! is_array($contact)) {
            return [];
        }

        return collect($contact)
            ->take(6)
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['label'] ?? '')) !== '' && trim((string) ($row['value'] ?? '')) !== '')
            ->map(fn ($row) => [
                'label' => trim((string) $row['label']),
                'value' => trim((string) $row['value']),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $footer
     * @return array{enabled: bool, description: string, copyright: string, background_color: string|null, text_color: string|null, columns: list<array{title: string, title_color: string|null, link_color: string|null, links: list<array{label: string, url: string}>}>, contact: list<array{label: string, value: string}>, social: list<array{platform: string, url: string}>}
     */
    private function footerPayload(?array $footer, Website $website): array
    {
        $defaults = [
            'enabled' => true,
            'description' => '',
            'copyright' => '© {year} '.$website->name.'. Todos los derechos reservados.',
            'background_color' => null,
            'text_color' => null,
            'columns' => [],
            'contact' => [],
            'social' => [],
        ];

        return $this->sanitizeFooter([...$defaults, ...($footer ?? [])]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{enabled: bool, show_on_mobile: bool, blocks: list<array<string, mixed>>, text_color: string, background_color: string}
     */
    private function cintilloPayloadFromData(array $data): array
    {
        return [
            'enabled' => (bool) ($data['cintillo_enabled'] ?? false),
            'show_on_mobile' => (bool) ($data['cintillo_show_on_mobile'] ?? true),
            'blocks' => $this->sanitizeBlocks($data['cintillo_blocks'] ?? []),
            'text_color' => $data['cintillo_text_color'] ?? '#ffffff',
            'background_color' => $data['cintillo_background_color'] ?? '#111827',
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{enabled: bool, show_on_mobile: bool, blocks: list<array<string, mixed>>, text_color: string, background_color: string}
     */
    private function cintilloFromSettings(array $settings): array
    {
        return [
            'enabled' => (bool) $settings['cintillo_enabled'],
            'show_on_mobile' => (bool) $settings['cintillo_show_on_mobile'],
            'blocks' => $settings['cintillo_blocks'],
            'text_color' => (string) $settings['cintillo_text_color'],
            'background_color' => (string) $settings['cintillo_background_color'],
        ];
    }

    /**
     * @return array{enabled: bool, show_on_mobile: bool, blocks: list<array<string, mixed>>, text_color: string, background_color: string}|null
     */
    private function storeCintilloOverride(Store $store): ?array
    {
        $value = StoreConfiguration::query()
            ->where('scope', ScopedConfigService::SCOPE_STORE)
            ->where('scope_id', $store->id)
            ->where('key', self::CINTILLO_CONFIG_KEY)
            ->value('value');

        if (! $value) {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        return [
            'enabled' => (bool) ($decoded['enabled'] ?? false),
            'show_on_mobile' => (bool) ($decoded['show_on_mobile'] ?? true),
            'blocks' => $this->sanitizeBlocks($decoded['blocks'] ?? []),
            'text_color' => $this->hexOrDefault($decoded['text_color'] ?? null, '#ffffff'),
            'background_color' => $this->hexOrDefault($decoded['background_color'] ?? null, '#111827'),
        ];
    }

    private function deleteStoreCintilloOverride(Store $store): void
    {
        StoreConfiguration::query()
            ->where('scope', ScopedConfigService::SCOPE_STORE)
            ->where('scope_id', $store->id)
            ->where('key', self::CINTILLO_CONFIG_KEY)
            ->delete();
    }

    private function hexOrDefault(mixed $value, string $default): string
    {
        $value = is_string($value) ? $value : '';

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? $value : $default;
    }

    private function resolveWebsite(int $websiteId): ?Website
    {
        return ($websiteId ? Website::find($websiteId) : null)
            ?? Website::where('is_default', true)->first()
            ?? Website::orderBy('sort_order')->first();
    }

    private function resolveStore(Website $website, int $storeId): ?Store
    {
        if (! $storeId) {
            return null;
        }

        return Store::query()
            ->where('website_id', $website->id)
            ->findOrFail($storeId);
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    private function websiteOptions(): Collection
    {
        return Website::orderBy('sort_order')->get(['id', 'name'])
            ->map(fn (Website $website) => ['id' => $website->id, 'label' => $website->name]);
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    private function storeOptions(Website $website): Collection
    {
        return Store::query()
            ->where('website_id', $website->id)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->map(fn (Store $store) => ['id' => $store->id, 'label' => $store->name]);
    }
}
