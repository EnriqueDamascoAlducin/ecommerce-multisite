<?php

namespace App\Providers;

use App\Domain\Payment\Gateways\MercadoPagoGateway;
use App\Domain\Payment\Gateways\OfflineGateway;
use App\Domain\Payment\Gateways\OpenpayGateway;
use App\Domain\Payment\PaymentGatewayRegistry;
use App\Domain\Store\StoreContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Sitio resuelto, compartido durante toda la petición.
        $this->app->singleton(StoreContext::class);

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
        $this->configureDefaults();
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
