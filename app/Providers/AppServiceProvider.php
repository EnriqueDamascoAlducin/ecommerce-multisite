<?php

namespace App\Providers;

use App\Domain\Payment\Gateways\MercadoPagoGateway;
use App\Domain\Payment\Gateways\OfflineGateway;
use App\Domain\Payment\Gateways\OpenpayGateway;
use App\Domain\Payment\PaymentGatewayRegistry;
use App\Domain\Payment\PaymentSettings;
use App\Domain\Store\StoreContext;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStore;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\StorefrontPage;
use App\Observers\StorefrontSeoCacheObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Sitio resuelto, compartido durante toda la petición.
        $this->app->singleton(StoreContext::class);

        // Configuración de pasarelas resuelta por website (admin) con fallback a env.
        $this->app->singleton(PaymentSettings::class);

        // Registro de pasarelas de pago. Cada gateway decide su disponibilidad
        // según la configuración (p. ej. Mercado Pago requiere access token).
        $this->app->singleton(PaymentGatewayRegistry::class, function (): PaymentGatewayRegistry {
            $registry = new PaymentGatewayRegistry;
            $registry->register(new OfflineGateway);
            $registry->register(new MercadoPagoGateway);
            $registry->register(new OpenpayGateway);

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Category::observe(StorefrontSeoCacheObserver::class);
        Product::observe(StorefrontSeoCacheObserver::class);
        ProductStore::observe(StorefrontSeoCacheObserver::class);
        Store::observe(StorefrontSeoCacheObserver::class);
        StoreDomain::observe(StorefrontSeoCacheObserver::class);
        StorefrontPage::observe(StorefrontSeoCacheObserver::class);

        $this->configureInertiaExceptionRendering();
        $this->configureDefaults();
    }

    private function configureInertiaExceptionRendering(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            if (
                $response->statusCode() !== 404
                || $response->request->is('admin', 'admin/*')
                || ! $response->request->routeIs('storefront.*')
            ) {
                return null;
            }

            return $response->render('storefront/error', [
                'status' => 404,
            ])->withSharedData();
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Fuera de producción, convierte cualquier carga perezosa (N+1) en una
        // excepción para detectarla en desarrollo y tests.
        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
