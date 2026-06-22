<?php

namespace App\Http\Middleware;

use App\Domain\Cart\CartService;
use App\Domain\Store\AdminScopeManager;
use App\Domain\Store\FooterSettingsService;
use App\Domain\Store\HeaderMenuService;
use App\Domain\Store\StoreContext;
use App\Models\Media;
use App\Models\Store;
use App\Models\StoreConfiguration;
use App\Models\Website;
use App\Models\WebsiteHeaderSettings;
use Illuminate\Http\Request;
use Inertia\Middleware;
use JsonException;

class HandleInertiaRequests extends Middleware
{
    private const CINTILLO_CONFIG_KEY = 'header.cintillo';

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user('web'),
                'roles' => $request->user('web')?->getRoleNames()->values() ?? [],
                'permissions' => $request->user('web')?->getAllPermissions()->pluck('name')->values() ?? [],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'store' => fn () => $this->currentStore(),
            'customer' => $request->user('customer')?->only(['id', 'name', 'email']),
            'cart' => fn () => $this->cartSummary(),
            'adminScope' => $this->adminScope($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Scope que el admin está configurando (null para invitados).
     *
     * @return array{current: array{type: string, id: int, label: string}, options: list<array{type: string, id: int, label: string}>}|null
     */
    private function adminScope(Request $request): ?array
    {
        $user = $request->user('web');

        if (! $user) {
            return null;
        }

        $manager = app(AdminScopeManager::class);

        return [
            'current' => [...$manager->current(), 'label' => $manager->currentLabel($user)],
            'options' => $manager->options($user),
        ];
    }

    /**
     * Sitio resuelto para el storefront (null en el admin y rutas sin resolver).
     *
     * @return array{website: array{id: int, code: string, name: string, logo_url: string|null, favicon_url: string|null}, store: array{id: int, code: string, name: string, logo_url: string|null}, locale: string|null, pathPrefix: string, menu: list<array<string, mixed>>, header: array<string, mixed>}|null
     */
    private function currentStore(): ?array
    {
        $context = app(StoreContext::class);

        if (! $context->hasStore()) {
            return null;
        }

        $store = $context->store();
        $website = $context->website();
        $store->loadMissing('media');
        $website->loadMissing('media');

        return [
            'website' => [
                'id' => $website->id,
                'code' => $website->code,
                'name' => $website->name,
                'logo_url' => $this->versionedMediaUrl($website->primaryMedia('logo')),
                'favicon_url' => $this->versionedMediaUrl($website->primaryMedia('favicon')),
            ],
            'store' => [
                'id' => $store->id,
                'code' => $store->code,
                'name' => $store->name,
                'logo_url' => $this->versionedMediaUrl($store->primaryMedia('logo')),
            ],
            'locale' => $context->storeView()?->locale,
            'pathPrefix' => $context->pathPrefix(),
            'menu' => $this->buildMenu($store),
            'header' => $this->headerConfig($website, $store),
        ];
    }

    /**
     * Configuración del encabezado. El cintillo puede sobreescribirse por tienda
     * y cae al valor base del website cuando no hay override.
     *
     * @return array{cintillo: array{enabled: bool, show_on_mobile: bool, blocks: list<array<string, mixed>>, text_color: string, background_color: string}, colors: array{header_text_color: string|null, header_background_color: string|null, menu_text_color: string|null, menu_background_color: string|null}, footer: array<string, mixed>}
     */
    private function headerConfig(Website $website, Store $store): array
    {
        $settings = WebsiteHeaderSettings::firstWhere('website_id', $website->id);
        $baseCintillo = [
            'enabled' => $settings?->cintillo_enabled ?? false,
            'show_on_mobile' => $settings?->cintillo_show_on_mobile ?? true,
            'blocks' => $settings?->cintillo_blocks ?? [],
            'text_color' => $settings?->cintillo_text_color ?? '#ffffff',
            'background_color' => $settings?->cintillo_background_color ?? '#111827',
        ];

        return [
            'cintillo' => $this->storeCintilloOverride($store) ?? $baseCintillo,
            'colors' => [
                'header_text_color' => $settings?->header_text_color,
                'header_background_color' => $settings?->header_background_color,
                'menu_text_color' => $settings?->menu_text_color,
                'menu_background_color' => $settings?->menu_background_color,
            ],
            'footer' => app(FooterSettingsService::class)->resolvedFor($website, $store, $settings?->footer_settings),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $footer
     * @return array{enabled: bool, description: string, copyright: string, background_color: string|null, text_color: string|null, columns: list<array<string, mixed>>, contact: list<array<string, string>>, social: list<array<string, string>>}
     */
    private function footerConfig(?array $footer, Website $website): array
    {
        $footer = [
            'enabled' => true,
            'description' => '',
            'copyright' => '© {year} '.$website->name.'. Todos los derechos reservados.',
            'background_color' => null,
            'text_color' => null,
            'columns' => [],
            'contact' => [],
            'social' => [],
            ...($footer ?? []),
        ];

        return [
            'enabled' => (bool) $footer['enabled'],
            'description' => (string) $footer['description'],
            'copyright' => (string) $footer['copyright'],
            'background_color' => $footer['background_color'],
            'text_color' => $footer['text_color'],
            'columns' => is_array($footer['columns']) ? $footer['columns'] : [],
            'contact' => is_array($footer['contact']) ? $footer['contact'] : [],
            'social' => is_array($footer['social']) ? $footer['social'] : [],
        ];
    }

    /**
     * Resumen del carrito para el badge del header (null fuera del storefront).
     *
     * @return array{count: int, total: string}|null
     */
    private function cartSummary(): ?array
    {
        if (! app(StoreContext::class)->hasStore()) {
            return null;
        }

        return app(CartService::class)->summary();
    }

    /**
     * Árbol del menú del header vía HeaderMenuService.
     *
     * @return list<array<string, mixed>>
     */
    private function buildMenu(Store $store): array
    {
        return app(HeaderMenuService::class)->buildTree($store);
    }

    /**
     * @return array{enabled: bool, show_on_mobile: bool, blocks: list<array<string, mixed>>, text_color: string, background_color: string}|null
     */
    private function storeCintilloOverride(Store $store): ?array
    {
        $value = StoreConfiguration::query()
            ->where('scope', 'store')
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
            'blocks' => is_array($decoded['blocks'] ?? null) ? $decoded['blocks'] : [],
            'text_color' => $this->hexOrDefault($decoded['text_color'] ?? null, '#ffffff'),
            'background_color' => $this->hexOrDefault($decoded['background_color'] ?? null, '#111827'),
        ];
    }

    private function hexOrDefault(mixed $value, string $default): string
    {
        $value = is_string($value) ? $value : '';

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? $value : $default;
    }

    private function versionedMediaUrl(?Media $media): ?string
    {
        if (! $media) {
            return null;
        }

        $separator = str_contains($media->url, '?') ? '&' : '?';

        return $media->url.$separator.'v='.$media->updated_at?->getTimestamp();
    }
}
