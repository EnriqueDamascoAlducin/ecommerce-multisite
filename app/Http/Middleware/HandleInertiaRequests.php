<?php

namespace App\Http\Middleware;

use App\Domain\Cart\CartService;
use App\Domain\Store\AdminScopeManager;
use App\Domain\Store\HeaderMenuService;
use App\Domain\Store\StoreContext;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
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
     * @return array{website: array{id: int, code: string, name: string}, store: array{id: int, code: string, name: string}, locale: string|null, pathPrefix: string, menu: list<array<string, mixed>>}|null
     */
    private function currentStore(): ?array
    {
        $context = app(StoreContext::class);

        if (! $context->hasStore()) {
            return null;
        }

        $store = $context->store();
        $website = $context->website();

        return [
            'website' => ['id' => $website->id, 'code' => $website->code, 'name' => $website->name],
            'store' => ['id' => $store->id, 'code' => $store->code, 'name' => $store->name],
            'locale' => $context->storeView()?->locale,
            'pathPrefix' => $context->pathPrefix(),
            'menu' => $this->buildMenu($store),
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
}
